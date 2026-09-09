<?php

declare(strict_types=1);

namespace Tests\Canonical;

use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainSkill;
use App\Modules\Academic\Commands\MaintainTeacherProfile;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\Academic\Models\TeacherProfile;
use App\Modules\Academic\Models\TeacherSkillAuthority;
use App\Modules\Identity\Models\Person;
use App\Support\Authorization\Actor;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsAcademicStructure;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsEnrollments;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\BuildsTeachers;
use Tests\TestCase;

/**
 * Base class for the canonical suite.
 *
 * The canonical suite is derived from the *current* implementation — commands,
 * schema, triggers, authorization and lifecycle rules — rather than from the
 * expectations of older tests. Its purpose is that a passing result is
 * meaningful, not that the count is large.
 *
 * Isolation comes from the locked strategy in docs/TESTING_STRATEGY_LOCK.md:
 * `RefreshDatabase` migrates once per process and rolls back a transaction per
 * test.
 *
 * Every fixture here is produced by the production commands, so a fixture
 * cannot encode state the domain would refuse in production.
 */
abstract class CanonicalTestCase extends TestCase
{
    use BuildsAcademicStructure;
    use BuildsActors;
    use BuildsEnrollments;
    use BuildsStudents;
    use BuildsTeachers;

    /** @param list<string> $capabilities */
    protected function actorWith(string $actorId, array $capabilities): Actor
    {
        return $this->grantedActor($actorId, $capabilities);
    }

    /** @return array{student: mixed, person: mixed} */
    protected function newStudent(array $ids = []): array
    {
        return $this->makeStudent($ids);
    }

    protected function sharedBranchId(): string
    {
        return $this->bootstrapBranchId();
    }

    protected function canonicalPersonWithAuthority(string $personId, array $capabilities = []): Person
    {
        return $this->personWithAuthority($personId, $capabilities);
    }

    /** @return array{person_id: string, employment_id: string, teacher_profile_id: string} */
    protected function newActiveTeacher(string $personId, ?string $branchId = null, string $keyPrefix = 'canon-teacher'): array
    {
        return $this->buildActiveTeacher($personId, $branchId, $keyPrefix);
    }

    /** @return array<string, string> */
    protected function newAcademicChain(Actor $officer, string $keyPrefix, int $capacity = 25, ?string $branchId = null): array
    {
        return $this->buildAcademicChain($officer, $keyPrefix, $capacity, $branchId);
    }

    /**
     * An active class with a teacher, ready to accept enrollments.
     *
     * @return array<string, string>
     */
    protected function newActiveClass(Actor $officer, string $keyPrefix, int $classCapacity = 2, int $offeringCapacity = 25): array
    {
        $delivery = $this->buildActiveClass($officer, $keyPrefix, $classCapacity, $offeringCapacity);

        // Make the class schedulable: a session needs an authorized, available
        // teacher whose assignment carries the session skill. Returning a class
        // that cannot hold a session would push this chain into every test.
        // Actor ids and idempotency keys are char(36) and this seam appends
        // suffixes such as '-w7-sk-appr' and '-teacher-approver'. Derive a
        // short, stable tag so a descriptive test prefix cannot overflow them.
        $tag = substr($keyPrefix, 0, 6).substr(md5($keyPrefix), 0, 4);

        $skillId = $this->newSkillId($officer, $tag.'-sk');
        $profileId = TeacherProfile::query()
            ->where('person_id', $delivery['teacher_person_id'])->value('id');

        if ($profileId !== null) {
            foreach (range(1, 7) as $weekday) {
                $this->makeTeacherSessionReady(
                    (string) $profileId, $skillId, $delivery['branch_id'],
                    $tag.'-w'.$weekday, $weekday
                );
            }
            $this->attributeSkillToAssignment($officer, $delivery['class_id'], $skillId, $tag.'-attr');
        }

        return $delivery + ['skill_id' => $skillId];
    }

    /** @return array{enrollment_id: string, correlation_id: string} */
    protected function newSeatRequest(Actor $requester, string $studentId, string $classId, string $key, ?string $offeringId = null): array
    {
        return $this->requestSeat($requester, $studentId, $classId, $key, $offeringId);
    }

    /**
     * A registered academic skill.
     *
     * Sessions require explicit subject/skill authority; the domain refuses to
     * schedule teaching with no stated subject.
     */
    protected function newSkillId(Actor $officer, string $key): string
    {
        // Skill registration is branchless governance and needs an
        // organization-wide `academic.skill` grant, which a branch-scoped
        // academic officer does not carry.
        $registrar = $this->actorWith('canon-skill-registrar', ['academic.skill']);

        return app(MaintainSkill::class)
            ->register($registrar, $key, ucfirst(str_replace('-', ' ', $key)), $key.'-skill')['skill_id'];
    }

    /**
     * Makes a teacher able to deliver a session for a skill.
     *
     * Scheduling checks a chain the domain will not infer: the skill must be
     * authorized for the teacher in that branch, the teacher must be available
     * on the session weekday, and the skill must be attributed to the
     * effective class assignment.
     */
    protected function makeTeacherSessionReady(
        string $teacherProfileId,
        string $skillId,
        string $branchId,
        string $keyPrefix,
        int $weekday
    ): void {
        // authorizeSkill is approval-side; declareAvailability is management-side.
        $approver = $this->actorWith($keyPrefix.'-sk-a', ['academic.teacher_approve']);
        $manager = $this->actorWith($keyPrefix.'-sk-m', ['academic.teacher_manage']);
        $profiles = app(MaintainTeacherProfile::class);
        $profile = TeacherProfile::query()->findOrFail($teacherProfileId);
        $from = CarbonImmutable::today()->subYear()->toDateString();

        if (! TeacherSkillAuthority::query()
            ->where('teacher_profile_id', $teacherProfileId)
            ->where('skill_id', $skillId)->where('branch_id', $branchId)->exists()) {
            $profiles->authorizeSkill(
                $approver, $profile, $skillId, $branchId, 'teach', $from, null,
                'evidence/'.$keyPrefix.'/skill', $keyPrefix.'-sk-auth'
            );
        }
        $profiles->declareAvailability(
            $manager, TeacherProfile::query()->findOrFail($teacherProfileId),
            $branchId, $weekday, '00:00', '23:59', $from, null, 'available', $keyPrefix.'-sk-avail'
        );
    }

    /** Attributes a skill to the class's effective teacher assignment. */
    protected function attributeSkillToAssignment(
        Actor $officer,
        string $classId,
        string $skillId,
        string $key
    ): void {
        $assignment = TeacherAssignment::query()
            ->where('class_id', $classId)->whereNull('effective_to')->firstOrFail();

        // Attribution is a teacher-management action; the scheduling officer
        // does not necessarily hold that capability.
        $manager = $this->actorWith($key.'-mgr', ['academic.teacher_manage', 'academic.schedule']);

        app(MaintainClass::class)
            ->assignSkill($manager, $assignment, $skillId, $key);
    }
}
