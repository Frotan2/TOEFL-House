#!/usr/bin/env php
<?php

/**
 * Library & Resources browser E2E provisioning.
 *
 * Creates the journey actors as NON-OWNER operators (no Owner role, no
 * wildcard capability set) with authority shaped exactly like the
 * feature-suite fixtures: the SeedsAuthority/BuildsActors test traits are the
 * provisioning engine, so an operator that works here works under the same
 * canonical access model the product uses — there is no parallel,
 * script-local authz.
 *
 * Actors (all verified persons homed on the bootstrap branch):
 *   e2e-library-librarian   resources.books, resources.asset,
 *                           resources.dispose_request, facilities.work
 *   e2e-library-approver-1  resources.dispose_approve, facilities.work_approve
 *   e2e-library-approver-2  resources.dispose_approve
 *   (borrower person)       no capabilities; circulation/custody counterparty
 *
 * Account passwords follow the provisioning convention of the disposable
 * verification databases (same shape as the structure journey accounts).
 *
 * Idempotent: once the accounts exist the script is a no-op. Re-run after
 * `php artisan migrate:fresh` to rebuild.
 *
 * Usage:
 *   DB_DATABASE=toefl_house_e2e php scripts/runtime/library-browser-provision.php
 */

declare(strict_types=1);

use App\Modules\Identity\Models\UserAccount;
use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\BuildsActors;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class LibraryBrowserProvisioner
{
    use BuildsActors;

    /** @return array<string, array{username: string, person_id: string}> */
    public function run(): array
    {
        if (! UserAccount::query()->where('username', 'e2e-library-librarian')->exists()) {
            $this->personWithAuthority('e2e-library-librarian', [
                'resources.books', 'resources.asset', 'resources.dispose_request', 'facilities.work',
            ]);
            $this->personWithAuthority('e2e-library-approver-1', [
                'resources.dispose_approve', 'facilities.work_approve',
            ]);
            $this->personWithAuthority('e2e-library-approver-2', [
                'resources.dispose_approve',
            ]);
            // A verified, branch-homed person with no capabilities: the
            // borrower/custodian counterparty the workspace directory offers.
            $this->personWithAuthority('e2e-library-borrower', []);

            $this->userForActor('e2e-library-librarian', 'e2e-library-librarian');
            $this->userForActor('e2e-library-approver-1', 'e2e-library-approver-1');
            $this->userForActor('e2e-library-approver-2', 'e2e-library-approver-2');

            echo "library browser E2E actors provisioned on DB '".config('database.connections.pgsql.database')."'\n";
        } else {
            echo "library browser E2E accounts already present\n";
        }

        return $this->manifest();
    }

    /** @return array<string, array{username: string, person_id: string}> */
    private function manifest(): array
    {
        return UserAccount::query()
            ->whereIn('username', ['e2e-library-librarian', 'e2e-library-approver-1', 'e2e-library-approver-2'])
            ->get()
            ->mapWithKeys(static fn (UserAccount $account): array => [$account->username => [
                'username' => $account->username,
                'person_id' => $account->person_id,
            ]])
            ->all();
    }
}

$manifest = (new LibraryBrowserProvisioner)->run();
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
