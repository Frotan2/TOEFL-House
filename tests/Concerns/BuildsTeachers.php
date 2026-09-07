<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Academic\Commands\MaintainTeacherProfile;
use App\Modules\Academic\Models\TeacherProfile;
use App\Modules\Academic\Models\TeacherProfileBranch;
use App\Modules\Academic\Models\TeacherQualification;
use App\Modules\Hr\Commands\MaintainContract;
use App\Modules\Hr\Commands\MaintainEmployment;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employment;
use App\Support\Authorization\Actor;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;

/**
 * Canonical teacher fixture.
 *
 * A person cannot simply be named as a teacher. `MaintainTeacherAssignment`
 * requires an *active canonical teacher profile*, and
 * `MaintainTeacherProfile::assertActivationFacts()` requires all of:
 *
 *     verified identity
 *   + active HR employment
 *   + a current verified qualification
 *   + an effective current-home branch authorization
 *
 * Tests previously called `assignTeacher` with a bare person and were rejected
 * with "the person has no active canonical teacher profile". That rejection is
 * the domain behaving correctly; the fixture was incomplete.
 *
 * Everything here goes through the production commands, so the fixture cannot
 * drift away from the rules it is meant to satisfy.
 */
trait BuildsTeachers
{
    /**
     * Produces a person with an active teacher profile in the given branch.
     *
     * @return array{person_id: string, employment_id: string, teacher_profile_id: string}
     */
    private function buildActiveTeacher(string $personId, ?string $branchId = null, string $keyPrefix = 'teacher'): array
    {
        $branchId ??= $this->bootstrapBranchId();
        $effectiveFrom = CarbonImmutable::today()->subMonths(3)->toDateString();

        // Identity: verified, homed in the branch. Set on insert because a
        // verified person is immutable (people_identity_guard).
        $this->personWithAuthority($personId, []);

        $hrOfficer = $this->grantedActor($keyPrefix.'-hr-officer', ['hr.employ']);
        $academicOfficer = $this->grantedActor($keyPrefix.'-teacher-manager', ['academic.teacher_manage']);
        // Verification, branch authorization and activation are approval-side
        // actions; a distinct capability keeps the separation of duties the
        // domain intends.
        $academicApprover = $this->grantedActor($keyPrefix.'-teacher-approver', ['academic.teacher_approve']);

        // HR employment must exist and be active before a profile can exist.
        $employment = app(MaintainEmployment::class)->employ(
            $hrOfficer,
            $personId,
            $keyPrefix.'-employ',
        );
        $employmentModel = Employment::query()->findOrFail($employment['employment_id']);

        // Hiring requires an active contract (hr.hire_requires_contract).
        $contractOfficer = $this->grantedActor($keyPrefix.'-hr-contract', ['hr.contract']);
        $contract = app(MaintainContract::class)->draft(
            $contractOfficer,
            $employmentModel,
            'Fixture instructor terms',
            $effectiveFrom,
            $keyPrefix.'-contract-draft',
        );
        app(MaintainContract::class)->sign(
            $contractOfficer,
            Contract::query()->findOrFail($contract['contract_id']),
            'evidence/'.$keyPrefix.'/contract',
            $keyPrefix.'-contract-sign',
        );

        app(MaintainEmployment::class)->hire(
            $hrOfficer,
            $employmentModel,
            $effectiveFrom,
            $keyPrefix.'-hire',
        );

        $profiles = app(MaintainTeacherProfile::class);
        $registered = $profiles->register(
            $academicOfficer,
            Employment::query()->findOrFail($employment['employment_id']),
            'Instructor',
            null,
            $keyPrefix.'-register',
        );
        $profile = TeacherProfile::query()->findOrFail($registered['teacher_profile_id']);

        // Activation facts, each required by assertActivationFacts().
        $qualification = $profiles->addQualification(
            $academicOfficer,
            $profile,
            'degree',
            'BA English',
            'Fixture University',
            'evidence/'.$keyPrefix.'/qualification',
            CarbonImmutable::today()->subYear()->toDateString(),
            null,
            $keyPrefix.'-qual',
        );
        $profiles->verifyQualification(
            $academicApprover,
            TeacherQualification::query()->findOrFail($qualification['qualification_id']),
            $keyPrefix.'-qual-verify',
        );

        // register() creates the current-home branch authorization effective
        // today. Tests legitimately assign teachers from earlier in the term,
        // so backdate that fixture row to the employment start. This widens
        // only fixture data; no production rule is relaxed.
        // The row's identity is immutable (teacher_authority_reference_guard),
        // so replace it rather than mutating it.
        $existing = TeacherProfileBranch::query()
            ->where('teacher_profile_id', $profile->id)
            ->where('branch_id', $branchId)
            ->firstOrFail();
        $attributes = $existing->getAttributes();
        $existing->delete();
        $attributes['effective_from'] = $effectiveFrom;
        TeacherProfileBranch::query()->create($attributes);

        $profiles->transition(
            $academicApprover,
            TeacherProfile::query()->findOrFail($profile->id),
            TeacherProfile::STATE_ACTIVE,
            'fixture activation',
            $keyPrefix.'-activate',
        );

        return [
            'person_id' => $personId,
            'employment_id' => $employment['employment_id'],
            'teacher_profile_id' => $profile->id,
        ];
    }

    /** Convenience: an Actor for a person built by buildActiveTeacher(). */
    private function teacherActor(string $personId, string $displayName = 'Teacher'): Actor
    {
        return new Actor($personId, $displayName);
    }

    /** A random-but-stable fixture identifier. */
    private function fixtureId(): string
    {
        return RandomIdentifier::new();
    }
}
