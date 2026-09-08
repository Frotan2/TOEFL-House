<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Identity\Commands\RegisterPerson;
use App\Modules\Identity\Models\Person;
use App\Support\Identifiers\RandomIdentifier;

/**
 * Unverified person intake for fixtures that need a person row before
 * verification. Person intake is the only lawful birth of a person row
 * (identity intake doctrine): RegisterPerson persists the active home branch
 * that every person-linked scope resolves from. Fixtures must not write
 * unprovenanced person rows and then expect command paths to work.
 */
trait RegistersPeople
{
    /**
     * Registers a person through the authoritative intake command, assigned
     * to the bootstrap branch (the fixture authority home).
     *
     * @return Person the registered, still-unverified person row
     */
    protected function newUnverifiedPerson(string $legalName, string $dateOfBirth = '1990-01-01', string $keyPrefix = 'person'): Person
    {
        $registered = app(RegisterPerson::class)->register(
            $this->identityAdministrator(),
            $legalName,
            $dateOfBirth,
            $this->bootstrapBranchId(),
            $keyPrefix.'-'.RandomIdentifier::new(),
        );

        return Person::query()->findOrFail($registered['person_id']);
    }
}
