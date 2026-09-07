<?php

declare(strict_types=1);

namespace Tests\Canonical;

use Tests\Concerns\BuildsAcademicStructure;
use Tests\Concerns\BuildsActors;
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
    // The shared fixture traits declare their helpers private, which keeps
    // them off the public surface of legacy tests. The canonical suite needs
    // them in subclasses, so they are re-exposed here as protected seams
    // rather than by widening the traits (which legacy tests still rely on).
    use BuildsAcademicStructure;
    use BuildsActors;
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
}
