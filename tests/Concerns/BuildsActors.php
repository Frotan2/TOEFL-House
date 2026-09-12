<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Authorization\Actor;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;

/**
 * Actor fixtures of the authority registry roles. Identity only: authority
 * itself is seeded into the canonical access model and resolved by the
 * server policy decision.
 */
trait BuildsActors
{
    use SeedsAuthority;

    /**
     * The HTTP-facing form of an authority fixture: a REAL, active employee
     * UserAccount linked to the seeded person, for use with
     * `$this->actingAs($this->userForActor($person))`.
     *
     * This is not a test double for authentication. The returned model is
     * the production identity (user_accounts, session guard) and every
     * request still runs the real EnsureEmployeeSession middleware, which
     * rebuilds the request Actor from the account's person_id; capabilities
     * are then resolved by the canonical AccessDecision from the rows
     * personWithAuthority() seeded. Tests that need capability denial must
     * therefore seed the person WITHOUT the capability — never here.
     *
     * Accepts the domain Actor value object returned by the role helpers,
     * the Person returned by personWithAuthority(), or a raw person id.
     * Reuses the existing active account when one exists
     * (user_accounts_one_active_per_person).
     */
    protected function userForActor(Actor|Person|string $actor, ?string $username = null): UserAccount
    {
        $personId = $actor instanceof Actor
            ? $actor->actorId
            : ($actor instanceof Person ? $actor->id : $actor);
        $personId = trim((string) $personId);

        $existing = UserAccount::query()
            ->where('person_id', $personId)
            ->where('account_state', UserAccount::STATE_ACTIVE)
            ->first();
        if ($existing instanceof UserAccount) {
            return $existing;
        }

        return UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username ?? 'employee-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $personId),
            'password_hash' => Hash::make('employee-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    protected function generalManager(string $actorId = 'gm-1'): Actor
    {
        $this->personWithAuthority($actorId, ['organization.structure.initiate']);

        return new Actor($actorId, 'General Manager');
    }

    protected function structureManager(string $scopeKey, string $actorId = 'mgr-1'): Actor
    {
        $this->personWithAuthority($actorId, []);
        $this->grantScopeAuthority($actorId, ['organization.structure.review'], 'organization', $this->organizationIdFromScopeKey($scopeKey));

        return new Actor($actorId, 'Structure Manager');
    }

    protected function structureOwner(string $scopeKey, string $actorId): Actor
    {
        $this->personWithAuthority($actorId, []);
        $this->grantScopeAuthority($actorId, ['organization.structure.approve'], 'organization', $this->organizationIdFromScopeKey($scopeKey));

        return new Actor($actorId, 'Owner');
    }

    protected function identityVerifier(string $actorId = 'idv-1'): Actor
    {
        $this->personWithAuthority($actorId, ['identity.verify']);

        return new Actor($actorId, 'Identity Verifier');
    }

    protected function identityAdministrator(string $actorId = 'ida-1'): Actor
    {
        $this->personWithAuthority($actorId, ['identity.admin']);

        return new Actor($actorId, 'Identity Administrator');
    }

    protected function actorWithoutAnyCapability(string $actorId = 'nobody-1'): Actor
    {
        $this->personWithAuthority($actorId, []);

        return new Actor($actorId, 'Unauthorized Actor');
    }

    protected function accessAdministrator(string $actorId = 'acc-1'): Actor
    {
        $this->personWithAuthority($actorId, [
            'access.grant', 'access.revoke', 'access.approve_org_wide',
            'access.define_policy', 'access.assign_position', 'access.delegate',
        ]);

        return new Actor($actorId, 'Access Administrator');
    }

    /** @param list<string> $capabilities */
    protected function actorWithStructureCapabilities(string $actorId, array $capabilities): Actor
    {
        $this->personWithAuthority($actorId, $capabilities);

        return new Actor($actorId, 'Multi-Capability Actor');
    }

    protected function privacyOfficer(string $actorId = 'priv-1'): Actor
    {
        $this->personWithAuthority($actorId, [
            'privacy.define_purpose', 'privacy.consent', 'privacy.disclose', 'privacy.export',
        ]);

        return new Actor($actorId, 'Privacy Officer');
    }

    protected function documentsOfficer(string $actorId = 'doc-1'): Actor
    {
        $this->personWithAuthority($actorId, [
            'documents.classify', 'documents.register', 'documents.verify', 'documents.retention',
        ]);

        return new Actor($actorId, 'Documents Officer');
    }

    protected function admissionsClerk(string $actorId = 'adm-reception-1'): Actor
    {
        $this->personWithAuthority($actorId, ['admissions.register', 'admissions.initiate']);

        return new Actor($actorId, 'Admissions Clerk');
    }

    protected function admissionsReviewer(string $actorId = 'adm-review-1'): Actor
    {
        $this->personWithAuthority($actorId, ['admissions.review']);

        return new Actor($actorId, 'Admissions Reviewer');
    }

    protected function admissionsApprover(string $actorId = 'adm-approve-1'): Actor
    {
        $this->personWithAuthority($actorId, ['admissions.approve']);

        return new Actor($actorId, 'Admissions Approver');
    }

    protected function studentManager(string $actorId = 'stu-mgr-1'): Actor
    {
        $this->personWithAuthority($actorId, ['students.manage', 'students.guardian']);

        return new Actor($actorId, 'Student Manager');
    }

    protected function studentReactivator(string $actorId = 'stu-react-1'): Actor
    {
        $this->personWithAuthority($actorId, ['students.reactivate']);

        return new Actor($actorId, 'Reactivation Approver');
    }

    protected function academicOfficer(string $actorId = 'acad-officer-1'): Actor
    {
        $this->personWithAuthority($actorId, ['academic.structure', 'academic.schedule', 'academic.enroll_approve', 'academic.attendance']);

        return new Actor($actorId, 'Academic Officer');
    }

    protected function placementOfficer(string $actorId = 'plc-officer-1'): Actor
    {
        $this->personWithAuthority($actorId, ['placement.catalog', 'placement.conduct', 'placement.score']);

        return new Actor($actorId, 'Placement Officer');
    }

    protected function placementRecommender(string $actorId = 'plc-recommender-1'): Actor
    {
        $this->personWithAuthority($actorId, ['placement.recommend']);

        return new Actor($actorId, 'Placement Recommender');
    }

    protected function placementModerator(string $actorId = 'plc-moderator-1'): Actor
    {
        $this->personWithAuthority($actorId, ['placement.moderate']);

        return new Actor($actorId, 'Placement Moderator');
    }

    protected function placementApprover(string $actorId = 'plc-approver-1'): Actor
    {
        $this->personWithAuthority($actorId, ['placement.approve']);

        return new Actor($actorId, 'Placement Approver');
    }

    protected function placementReleaser(string $actorId = 'plc-releaser-1'): Actor
    {
        $this->personWithAuthority($actorId, ['placement.release']);

        return new Actor($actorId, 'Placement Releaser');
    }

    protected function enrollmentClerk(string $actorId = 'acad-clerk-1'): Actor
    {
        $this->personWithAuthority($actorId, ['academic.enroll']);

        return new Actor($actorId, 'Enrollment Clerk');
    }

    /** @param list<string> $capabilities */
    protected function grantedActor(string $actorId, array $capabilities): Actor
    {
        $this->personWithAuthority($actorId, $capabilities);

        return new Actor($actorId, 'Granted Actor');
    }

    protected function organizationIdFromScopeKey(string $scopeKey): string
    {
        return str_contains($scopeKey, ':') ? explode(':', $scopeKey, 2)[1] : $this->bootstrapOrganizationId;
    }
}
