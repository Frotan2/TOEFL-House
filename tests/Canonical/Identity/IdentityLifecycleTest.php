<?php

declare(strict_types=1);

namespace Tests\Canonical\Identity;

use App\Modules\Identity\Commands\DeactivateUserAccount;
use App\Modules\Identity\Commands\LinkUserAccount;
use App\Modules\Identity\Commands\VerifyPerson;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Tests\Canonical\CanonicalTestCase;

final class IdentityLifecycleTest extends CanonicalTestCase
{
    private function newPerson(string $legalName = 'Sara Ahmadi'): Person
    {
        return Person::query()->create([
            'id' => RandomIdentifier::new(),
            'legal_name' => $legalName,
            'date_of_birth' => '1998-04-12',
            'verification_state' => Person::VERIFICATION_UNVERIFIED,
        ]);
    }

    public function test_verification_writes_identity_evidence_once_and_audits_it(): void
    {
        $person = $this->newPerson();
        $outcome = app(VerifyPerson::class)->verify(
            $this->actorWith('canon-identity-verifier', ['identity.verify']),
            $person,
            'passport-canon-8723',
            'documents/passport-canon-8723',
            RandomIdentifier::new(),
        );

        $this->assertSame($person->id, $outcome['person_id']);
        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'verification_state' => 'verified',
            'identity_key' => 'passport-canon-8723',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'identity.verify',
            'target_type' => 'person',
            'target_id' => $person->id,
        ]);
    }

    public function test_account_linking_requires_verified_identity(): void
    {
        $person = $this->newPerson('Unverified Person');

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('user account requires a verified person');
        app(LinkUserAccount::class)->link(
            $this->actorWith('canon-identity-admin', ['identity.admin']),
            $person,
            'unverified.canon.user',
            RandomIdentifier::new(),
        );
    }

    public function test_account_linking_persists_active_account_and_audit_evidence(): void
    {
        $person = $this->newPerson();
        app(VerifyPerson::class)->verify($this->actorWith('canon-identity-verifier-2', ['identity.verify']), $person, 'id-card-canon-5512', 'documents/id-card-canon-5512', RandomIdentifier::new());

        $outcome = app(LinkUserAccount::class)->link($this->actorWith('canon-identity-admin-2', ['identity.admin']), $person, 'sara.canon', RandomIdentifier::new());

        $this->assertDatabaseHas('user_accounts', [
            'id' => $outcome['account_id'],
            'person_id' => $person->id,
            'username' => 'sara.canon',
            'account_state' => 'active',
        ]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'identity.link_account', 'target_type' => 'person', 'target_id' => $person->id]);
    }

    public function test_deactivation_is_stateful_and_retains_account_history(): void
    {
        $person = $this->newPerson();
        app(VerifyPerson::class)->verify($this->actorWith('canon-identity-verifier-3', ['identity.verify']), $person, 'id-card-canon-9001', 'documents/id-card-canon-9001', RandomIdentifier::new());
        $linked = app(LinkUserAccount::class)->link($this->actorWith('canon-identity-admin-3', ['identity.admin']), $person, 'leaving.canon.user', RandomIdentifier::new());
        $account = UserAccount::query()->findOrFail($linked['account_id']);

        $outcome = app(DeactivateUserAccount::class)->deactivate($this->actorWith('canon-identity-admin-4', ['identity.admin']), $account, 'employment ended', RandomIdentifier::new());

        $this->assertSame('deactivated', $outcome['account_state']);
        $this->assertDatabaseHas('user_accounts', ['id' => $account->id, 'account_state' => 'deactivated']);
        $this->assertSame(1, UserAccount::query()->where('id', $account->id)->count());
        $this->assertDatabaseHas('audit_events', ['operation' => 'identity.deactivate_account', 'target_type' => 'user_account', 'target_id' => $account->id]);
    }

    public function test_deactivation_of_a_deactivated_account_is_rejected(): void
    {
        $person = $this->newPerson();
        app(VerifyPerson::class)->verify($this->actorWith('canon-identity-verifier-4', ['identity.verify']), $person, 'id-card-canon-7777', 'documents/id-card-canon-7777', RandomIdentifier::new());
        $linked = app(LinkUserAccount::class)->link($this->actorWith('canon-identity-admin-5', ['identity.admin']), $person, 'double.canon.user', RandomIdentifier::new());
        $account = UserAccount::query()->findOrFail($linked['account_id']);
        app(DeactivateUserAccount::class)->deactivate($this->actorWith('canon-identity-admin-6', ['identity.admin']), $account, 'first deactivation', RandomIdentifier::new());

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('only an active account can be deactivated');
        app(DeactivateUserAccount::class)->deactivate($this->actorWith('canon-identity-admin-7', ['identity.admin']), $account, 'second deactivation', RandomIdentifier::new());
    }

    public function test_unprivileged_identity_verification_is_denied_without_state_change(): void
    {
        $person = $this->newPerson('Target Person');

        try {
            app(VerifyPerson::class)->verify($this->actorWith('canon-identity-nobody', []), $person, 'id-card-canon-1', 'documents/x', RandomIdentifier::new());
            $this->fail('verification must be denied without identity.verify');
        } catch (AuthorizationDenied) {
        }

        $this->assertDatabaseHas('people', ['id' => $person->id, 'verification_state' => 'unverified']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'identity.verify.denied', 'target_type' => 'person', 'target_id' => $person->id]);
    }
}
