<?php

declare(strict_types=1);

namespace Tests\Canonical;

use App\Modules\Identity\Models\Person;
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
    protected function actorWith(string $actorId, array $capabilities): \App\Support\Authorization\Actor
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
    protected function newAcademicChain(\App\Support\Authorization\Actor $officer, string $keyPrefix, int $capacity = 25, ?string $branchId = null): array
    {
        return $this->buildAcademicChain($officer, $keyPrefix, $capacity, $branchId);
    }

    /**
     * An active class with a teacher, ready to accept enrollments.
     *
     * @return array<string, string>
     */
    protected function newActiveClass(\App\Support\Authorization\Actor $officer, string $keyPrefix, int $classCapacity = 2, int $offeringCapacity = 25): array
    {
        return $this->buildActiveClass($officer, $keyPrefix, $classCapacity, $offeringCapacity);
    }

    /** @return array{enrollment_id: string, correlation_id: string} */
    protected function newSeatRequest(\App\Support\Authorization\Actor $requester, string $studentId, string $classId, string $key, ?string $offeringId = null): array
    {
        return $this->requestSeat($requester, $studentId, $classId, $key, $offeringId);
    }

    /**
     * A registered academic skill.
     *
     * Sessions require explicit subject/skill authority; the domain refuses to
     * schedule teaching with no stated subject.
     */
    protected function newSkillId(\App\Support\Authorization\Actor $officer, string $key): string
    {
        return app(\App\Modules\Academic\Commands\MaintainSkill::class)
            ->register($officer, $key, ucfirst(str_replace('-', ' ', $key)), $key.'-skill')['skill_id'];
    }
}
