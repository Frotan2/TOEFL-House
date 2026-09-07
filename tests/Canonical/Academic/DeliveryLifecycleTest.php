<?php

declare(strict_types=1);

namespace Tests\Canonical\Academic;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Commands\RecordAttendance;
use App\Modules\Academic\Models\AttendanceFact;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSession;
use App\Modules\Academic\Models\Enrollment;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Queries\ClassRosterQuery;
use App\Modules\Students\Commands\TransitionStudentStatus;
use App\Modules\Students\Models\Student;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

final class DeliveryLifecycleTest extends CanonicalTestCase
{
    public function test_program_versions_are_immutable_and_class_lifecycle_requires_the_academic_chain(): void
    {
        $officer = $this->actorWith('canon-academic-structure', ['academic.structure', 'academic.schedule', 'academic.teacher_manage']);
        $program = app(MaintainAcademicStructure::class)->defineProgram($officer, 'TOEFL Fundamentals', 'canon-academic-program');
        /** @var Program $programRow */
        $programRow = Program::query()->findOrFail($program['program_id']);
        $first = app(MaintainAcademicStructure::class)->publishVersion($officer, $programRow, 'v1 rules', 'canon-academic-v1');
        $second = app(MaintainAcademicStructure::class)->publishVersion($officer, $programRow, 'v2 rules', 'canon-academic-v2');

        $this->assertSame(1, $first['version_no']);
        $this->assertSame(2, $second['version_no']);
        $this->assertSame('published', $programRow->refresh()->lifecycle_state);

        $this->expectException(QueryException::class);
        DB::statement('UPDATE program_versions SET summary = ? WHERE id = ?', ['tampered', $first['version_id']]);
    }

    public function test_planned_class_cannot_skip_to_active_and_active_class_requires_a_teacher(): void
    {
        $officer = $this->actorWith('canon-academic-lifecycle', ['academic.structure', 'academic.schedule', 'academic.teacher_manage']);
        $chain = $this->newAcademicChain($officer, 'canon-delivery-lifecycle', 10);
        $class = app(MaintainClass::class)->defineClass($officer, $chain['program_version_id'], $chain['period_id'], 2, 'canon-delivery-class', null, $this->sharedBranchId());
        $classRow = ClassModel::query()->findOrFail($class['class_id']);

        try {
            app(MaintainClass::class)->transition($officer, $classRow, 'active', 'canon-delivery-skip');
            $this->fail('planned -> active must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.class_transition_forbidden', $rejection->errorCode());
        }

        app(MaintainClass::class)->transition($officer, $classRow, 'published', 'canon-delivery-publish');
        try {
            app(MaintainClass::class)->transition($officer, $classRow, 'active', 'canon-delivery-no-teacher');
            $this->fail('a class without a teacher must not activate');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.class_needs_teacher', $rejection->errorCode());
        }

        $this->newActiveTeacher('canon-delivery-teach', $this->sharedBranchId(), 'canon-delivery');
        app(MaintainClass::class)->assignTeacher($officer, $classRow, 'canon-delivery-teach', new CarbonImmutable('2026-09-01'), null, 'canon-delivery-assignment');
        app(MaintainClass::class)->transition($officer, $classRow, 'active', 'canon-delivery-active');
        $this->assertDatabaseHas('classes', ['id' => $classRow->id, 'lifecycle_state' => 'active']);

        // Scheduling needs an authorized, available teacher whose assignment
        // carries the session skill. This test builds its class by hand to
        // exercise the lifecycle guards, so it wires that chain explicitly.
        $deliverySkillId = $this->newSkillId($officer, 'canon-delivery-sk');
        $profileId = (string) \App\Modules\Academic\Models\TeacherProfile::query()
            ->where('person_id', 'canon-delivery-teach')->value('id');
        foreach (range(1, 7) as $weekday) {
            $this->makeTeacherSessionReady(
                $profileId, $deliverySkillId, $this->sharedBranchId(),
                'canon-dlv-w'.$weekday, $weekday
            );
        }
        $this->attributeSkillToAssignment($officer, $classRow->id, $deliverySkillId, 'canon-dlv-attr');

        $session = app(MaintainClass::class)->scheduleSession($officer, $classRow, new CarbonImmutable('2026-09-07'), '09:00', '11:00', 'canon-delivery-session', $deliverySkillId);
        $this->assertDatabaseHas('class_sessions', ['id' => $session['session_id'], 'class_id' => $classRow->id]);

        // A class carrying future sessions may not be cancelled: cancelling it
        // would strand scheduled teaching. Assert the refusal rather than
        // assuming cancellation always succeeds.
        try {
            app(MaintainClass::class)->transition($officer, $classRow, 'cancelled', 'canon-delivery-cancel-blocked');
            $this->fail('a class with future sessions must not be cancellable');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.class_future_sessions', $rejection->errorCode());
        }
        $this->assertDatabaseHas('classes', ['id' => $classRow->id, 'lifecycle_state' => 'active']);

        // A class whose only session has already been delivered carries no
        // future sessions, so the same transition is then allowed. This proves
        // the guard is about outstanding teaching, not the class itself.
        $past = $this->newActiveClass($officer, 'canon-delivery-past', 2);
        $pastClass = ClassModel::query()->findOrFail($past['class_id']);
        app(MaintainClass::class)->scheduleSession(
            $officer,
            $pastClass,
            new CarbonImmutable('2026-09-02'),
            '09:00',
            '11:00',
            'canon-delivery-past-session',
            $past['skill_id']
        );

        app(MaintainClass::class)->transition($officer, $pastClass, 'cancelled', 'canon-delivery-cancel');
        $classRow = $pastClass;
        $this->assertDatabaseHas('classes', ['id' => $classRow->id, 'lifecycle_state' => 'cancelled']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'academic.class.transition', 'target_type' => 'class', 'target_id' => $classRow->id]);
    }

    public function test_duplicate_seat_capacity_and_transfer_preserve_enrollment_history(): void
    {
        $officer = $this->actorWith('canon-academic-enroll', ['academic.structure', 'academic.schedule', 'academic.teacher_manage', 'academic.enroll', 'academic.enroll_approve']);
        $delivery = $this->newActiveClass($officer, 'canon-delivery-enroll', 2);
        $classId = $delivery['class_id'];
        $studentA = $this->newStudent()['student'];
        $studentB = $this->newStudent()['student'];
        $studentC = $this->newStudent()['student'];
        $clerk = $this->actorWith('canon-enrollment-clerk', ['academic.enroll']);

        $seatA = app(MaintainEnrollment::class)->request($clerk, $studentA->id, $classId, 'canon-seat-a');
        app(MaintainEnrollment::class)->activate($officer, Enrollment::query()->findOrFail($seatA['enrollment_id']), 'canon-seat-a-active');

        try {
            app(MaintainEnrollment::class)->request($clerk, $studentA->id, $classId, 'canon-seat-duplicate');
            $this->fail('duplicate seat must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.enrollment_seat_exists', $rejection->errorCode());
        }

        $seatB = app(MaintainEnrollment::class)->request($clerk, $studentB->id, $classId, 'canon-seat-b');
        app(MaintainEnrollment::class)->activate($officer, Enrollment::query()->findOrFail($seatB['enrollment_id']), 'canon-seat-b-active');
        // Capacity is asserted at REQUEST time against 'requested', 'active'
        // and 'frozen' claims (EnrollmentConstraints::assertCapacity), so the
        // third seat is refused before it can ever reach activation.
        try {
            app(MaintainEnrollment::class)->request($clerk, $studentC->id, $classId, 'canon-seat-c');
            $this->fail('capacity of two must be exhausted at request time');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.class_full', $rejection->errorCode());
        }

        // The refused request left no row at all, which is the stronger
        // guarantee: a rejected seat claim must not persist partially.
        $this->assertSame(
            2,
            \Illuminate\Support\Facades\DB::table('enrollments')->where('class_id', $classId)->count(),
            'a refused seat request must not create an enrollment row'
        );

        $class2 = $this->newActiveClass($officer, 'canon-delivery-transfer', 5)['class_id'];
        $transferred = app(MaintainEnrollment::class)->transfer($officer, Enrollment::query()->findOrFail($seatA['enrollment_id']), $class2, 'canon-seat-transfer');
        $this->assertDatabaseHas('enrollments', ['id' => $seatA['enrollment_id'], 'lifecycle_state' => 'transferred']);
        $this->assertDatabaseHas('enrollments', ['id' => $transferred['enrollment_id'], 'lifecycle_state' => 'requested', 'class_id' => $class2]);

        $roster = (new ClassRosterQuery)->roster($classId);
        $this->assertSame(1, $roster['active_seats']);
        $this->assertCount(1, $roster['teachers']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'academic.enrollment.transfer']);
    }

    public function test_suspended_student_cannot_request_an_enrollment(): void
    {
        $officer = $this->actorWith('canon-suspended-officer', ['academic.structure', 'academic.schedule', 'academic.teacher_manage', 'academic.enroll']);
        $delivery = $this->newActiveClass($officer, 'canon-suspended', 2);
        $classId = $delivery['class_id'];
        $student = $this->newStudent()['student'];
        app(TransitionStudentStatus::class)->suspend(
            $this->actorWith('canon-student-manager', ['students.manage', 'students.hold']),
            Student::query()->findOrFail($student->id),
            'attendance',
            'canon-suspend',
        );

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('the student is not currently active for this operation');
        app(MaintainEnrollment::class)->request($this->actorWith('canon-suspended-clerk', ['academic.enroll']), $student->id, $classId, 'canon-suspended-enroll');
    }

    public function test_attendance_corrections_are_append_only_and_require_reason(): void
    {
        $officer = $this->actorWith('canon-attendance-officer', ['academic.structure', 'academic.schedule', 'academic.teacher_manage', 'academic.enroll', 'academic.enroll_approve', 'academic.attendance']);
        $delivery = $this->newActiveClass($officer, 'canon-attendance', 2);
        $classId = $delivery['class_id'];
        $student = $this->newStudent()['student'];
        $seat = app(MaintainEnrollment::class)->request($officer, $student->id, $classId, 'canon-attendance-enroll');
        app(MaintainEnrollment::class)->activate($officer, Enrollment::query()->findOrFail($seat['enrollment_id']), 'canon-attendance-activate');
        $session = app(MaintainClass::class)->scheduleSession($officer, ClassModel::query()->findOrFail($classId), new CarbonImmutable('2026-09-08'), '09:00', '11:00', 'canon-attendance-session', $delivery['skill_id']);
        $sessionRow = ClassSession::query()->findOrFail($session['session_id']);

        $fact = app(RecordAttendance::class)->record($officer, $sessionRow, Enrollment::query()->findOrFail($seat['enrollment_id']), 'present', 'canon-attendance-record');
        $correction = app(RecordAttendance::class)->correct($officer, AttendanceFact::query()->findOrFail($fact['fact_id']), 'late', 'verified late arrival', 'canon-attendance-correct');

        $this->assertDatabaseHas('attendance_facts', ['id' => $correction['fact_id'], 'corrects_id' => $fact['fact_id'], 'status' => 'late']);
        $this->assertDatabaseHas('attendance_facts', ['id' => $fact['fact_id'], 'status' => 'present']);

        try {
            app(RecordAttendance::class)->correct($officer, AttendanceFact::query()->findOrFail($fact['fact_id']), 'absent', '', 'canon-attendance-no-reason');
            $this->fail('correction without reason must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.attendance_correction_reason', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement("UPDATE attendance_facts SET status = 'absent' WHERE id = ?", [$fact['fact_id']]);
    }

    public function test_frozen_enrollment_cannot_take_attendance_or_transfer(): void
    {
        $officer = $this->actorWith('canon-frozen-officer', ['academic.structure', 'academic.schedule', 'academic.teacher_manage', 'academic.enroll', 'academic.enroll_approve', 'academic.attendance']);
        $delivery = $this->newActiveClass($officer, 'canon-frozen', 2);
        $classId = $delivery['class_id'];
        $student = $this->newStudent()['student'];
        $seat = app(MaintainEnrollment::class)->request($officer, $student->id, $classId, 'canon-frozen-enroll');
        app(MaintainEnrollment::class)->activate($officer, Enrollment::query()->findOrFail($seat['enrollment_id']), 'canon-frozen-activate');
        $session = app(MaintainClass::class)->scheduleSession($officer, ClassModel::query()->findOrFail($classId), new CarbonImmutable('2026-09-09'), '09:00', '11:00', 'canon-frozen-session', $delivery['skill_id']);
        $sessionRow = ClassSession::query()->findOrFail($session['session_id']);

        app(MaintainEnrollment::class)->freeze($officer, Enrollment::query()->findOrFail($seat['enrollment_id']), 'student requested a term break', 'canon-frozen-freeze');
        try {
            app(RecordAttendance::class)->record($officer, $sessionRow, Enrollment::query()->findOrFail($seat['enrollment_id']), 'present', 'canon-frozen-attendance');
            $this->fail('a frozen enrollment must not take attendance');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.attendance_enrollment_not_active', $rejection->errorCode());
        }

        // Transfer to a REAL class, so the rejection proves the frozen-state
        // rule rather than merely a missing target.
        $target = $this->newActiveClass($officer, 'canon-frozen-tgt', 2)['class_id'];

        try {
            app(MaintainEnrollment::class)->transfer($officer, Enrollment::query()->findOrFail($seat['enrollment_id']), $target, 'canon-frozen-transfer');
            $this->fail('frozen -> transferred must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.enrollment_transition_forbidden', $rejection->errorCode());
        }
        $this->assertDatabaseHas('enrollments', ['id' => $seat['enrollment_id'], 'lifecycle_state' => 'frozen']);
    }

    public function test_unprivileged_actor_cannot_define_academic_structure_and_no_row_is_persisted(): void
    {
        $nobody = $this->actorWith('canon-academic-nobody', []);

        $this->expectException(AuthorizationDenied::class);
        $this->expectExceptionMessage('an organization-wide authority grant is required');
        try {
            app(MaintainAcademicStructure::class)->definePeriod($nobody, 'Canonical Rogue Period', new CarbonImmutable('2027-01-01'), new CarbonImmutable('2027-06-01'), 'canon-period-denied');
        } finally {
            $this->assertDatabaseHas('audit_events', ['operation' => 'academic.period.define.denied', 'actor_id' => 'canon-academic-nobody']);
            $this->assertDatabaseMissing('academic_periods', ['name' => 'Canonical Rogue Period']);
        }
    }
}
