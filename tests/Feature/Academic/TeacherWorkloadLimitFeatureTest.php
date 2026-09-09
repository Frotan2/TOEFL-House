<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainTeacherAssignment;
use App\Modules\Academic\Commands\MaintainTeacherProfile;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\TeacherProfile;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsAcademicStructure;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsSessions;
use Tests\Concerns\BuildsTeachers;
use Tests\TestCase;

/**
 * Regression for TeacherAuthority workload admission.
 *
 * Existing assigned sessions are the teacher's workload. The guard must count
 * those sessions, not unrelated/unassigned sessions. This test drives the
 * canonical class/assignment/skill/availability/session path and proves that
 * a second two-hour session is rejected by a two-hour weekly limit.
 */
final class TeacherWorkloadLimitFeatureTest extends TestCase
{
    use BuildsActors;
    use BuildsAcademicStructure;
    use BuildsSessions;
    use BuildsTeachers;

    public function test_assigned_sessions_count_toward_the_weekly_teacher_workload_limit(): void
    {
        $branchId = $this->bootstrapBranchId();
        $structureOfficer = $this->academicOfficer('workload-structure-officer');
        $chain = $this->buildAcademicChain($structureOfficer, 'teacher-workload', 25, $branchId);

        $class = app(MaintainClass::class)->defineClass(
            $structureOfficer,
            $chain['program_version_id'],
            $chain['period_id'],
            20,
            'teacher-workload-class',
            $chain['level_id'],
            $branchId,
            $chain['offering_id'],
        );
        $classModel = ClassModel::query()->findOrFail($class['class_id']);

        $teacher = $this->buildActiveTeacher('teacher-workload-person', $branchId, 'workload-teacher');
        $assignmentOfficerId = 'workload-assignment-officer';
        $assignmentOfficer = $this->grantedActor($assignmentOfficerId, ['academic.schedule']);
        $this->grantScopeAuthority($assignmentOfficerId, ['academic.schedule'], 'branch', $branchId);

        app(MaintainTeacherAssignment::class)->assignTeacher(
            $assignmentOfficer,
            $classModel,
            $teacher['person_id'],
            CarbonImmutable::today()->startOfWeek(),
            null,
            'teacher-workload-assign',
        );
        app(MaintainClass::class)->transition(
            $assignmentOfficer,
            $classModel,
            'active',
            'teacher-workload-class-active',
        );

        $skillId = $this->makeClassSchedulable($assignmentOfficer, $classModel->id, $branchId, 'teacher-workload-skill');
        $profile = TeacherProfile::query()->findOrFail($teacher['teacher_profile_id']);
        $managerId = 'teacher-workload-manager';
        $manager = $this->grantedActor($managerId, ['academic.teacher_manage']);
        $this->grantScopeAuthority($managerId, ['academic.teacher_manage'], 'branch', $branchId);

        app(MaintainTeacherProfile::class)->setWorkloadLimit(
            $manager,
            $profile,
            $branchId,
            '2.00',
            CarbonImmutable::today()->subDay()->toDateString(),
            null,
            'evidence/teacher-workload/limit',
            'teacher-workload-limit',
        );

        $monday = CarbonImmutable::today()->startOfWeek();
        app(MaintainClass::class)->scheduleSession(
            $assignmentOfficer,
            $classModel,
            $monday,
            '09:00',
            '11:00',
            'teacher-workload-session-a',
            $skillId,
        );

        try {
            app(MaintainClass::class)->scheduleSession(
                $assignmentOfficer,
                $classModel,
                $monday->addDay(),
                '09:00',
                '11:00',
                'teacher-workload-session-b',
                $skillId,
            );
            $this->fail('assigned workload must block the second session once the weekly limit is reached');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('scheduling.teacher_delivery_unauthorized', $rejection->errorCode());
        }

        $this->assertDatabaseCount('class_sessions', 1);
    }
}
