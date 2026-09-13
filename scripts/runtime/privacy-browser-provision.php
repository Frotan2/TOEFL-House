#!/usr/bin/env php
<?php

/**
 * Privacy & Consent browser E2E provisioning.
 *
 * Creates the journey actors as NON-OWNER operators (no Owner role, no
 * wildcard capability set) with authority shaped exactly like the
 * feature-suite fixtures: the SeedsAuthority/BuildsActors test traits are the
 * provisioning engine, so an operator that works here works under the same
 * canonical access model the product uses — there is no parallel,
 * script-local authz.
 *
 * Actors (all verified persons homed on the bootstrap branch):
 *   e2e-privacy-officer        privacy.define_purpose + privacy.consent +
 *                              privacy.disclose + privacy.export — defines the
 *                              purpose catalog, records and drives consents,
 *                              records disclosures, exports one subject and
 *                              REQUESTS an organization-wide export. It holds
 *                              no approval capability at all, so the staged
 *                              chain can never be signed by its requester.
 *   e2e-privacy-approver-one   privacy.approve_bulk_export only — the first
 *                              distinct signature.
 *   e2e-privacy-approver-two   privacy.approve_bulk_export only — the second
 *                              distinct signature that approves the request.
 *   e2e-privacy-subject        no capabilities whatsoever — the consent
 *                              subject. It has an account so the journey can
 *                              prove the subject-side boundary: the workspace
 *                              projection is denied (fail-closed), yet the
 *                              subject may submit their OWN consent while
 *                              every officer-only act is refused.
 *
 * Account passwords follow the provisioning convention of the disposable
 * verification databases (same shape as the documents journey accounts).
 *
 * Idempotent: once the accounts exist the script is a no-op. Re-run after
 * `php artisan migrate:fresh` to rebuild.
 *
 * Usage:
 *   DB_DATABASE=toefl_house_e2e php scripts/runtime/privacy-browser-provision.php
 */

declare(strict_types=1);

use App\Modules\Identity\Models\UserAccount;
use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\BuildsActors;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class PrivacyBrowserProvisioner
{
    use BuildsActors;

    /** @return array<string, array{username: string, person_id: string}> */
    public function run(): array
    {
        if (! UserAccount::query()->where('username', 'e2e-privacy-officer')->exists()) {
            $this->personWithAuthority('e2e-privacy-officer', [
                'privacy.define_purpose', 'privacy.consent', 'privacy.disclose', 'privacy.export',
            ]);
            $this->personWithAuthority('e2e-privacy-approver-one', ['privacy.approve_bulk_export']);
            $this->personWithAuthority('e2e-privacy-approver-two', ['privacy.approve_bulk_export']);
            // A verified, branch-homed person with no capabilities: the
            // consent subject the officer records consents about.
            $this->personWithAuthority('e2e-privacy-subject', []);

            $this->userForActor('e2e-privacy-officer', 'e2e-privacy-officer');
            $this->userForActor('e2e-privacy-approver-one', 'e2e-privacy-approver-one');
            $this->userForActor('e2e-privacy-approver-two', 'e2e-privacy-approver-two');
            // The subject signs in to exercise its own consent: the journey
            // proves the subject-side acts are allowed while the workspace
            // projection and every officer-only act stay denied.
            $this->userForActor('e2e-privacy-subject', 'e2e-privacy-subject');

            echo "privacy browser E2E actors provisioned on DB '".config('database.connections.pgsql.database')."'\n";
        } else {
            echo "privacy browser E2E accounts already present\n";
        }

        return $this->manifest();
    }

    /** @return array<string, array{username: string, person_id: string}> */
    private function manifest(): array
    {
        return UserAccount::query()
            ->whereIn('username', [
                'e2e-privacy-officer',
                'e2e-privacy-approver-one',
                'e2e-privacy-approver-two',
                'e2e-privacy-subject',
            ])
            ->get()
            ->mapWithKeys(static fn (UserAccount $account): array => [$account->username => [
                'username' => $account->username,
                'person_id' => $account->person_id,
            ]])
            ->all();
    }
}

$manifest = (new PrivacyBrowserProvisioner)->run();
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
