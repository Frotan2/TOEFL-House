#!/usr/bin/env php
<?php

/**
 * Documents & Evidence browser E2E provisioning.
 *
 * Creates the journey actors as NON-OWNER operators (no Owner role, no
 * wildcard capability set) with authority shaped exactly like the
 * feature-suite fixtures: the SeedsAuthority/BuildsActors test traits are the
 * provisioning engine, so an operator that works here works under the same
 * canonical access model the product uses — there is no parallel,
 * script-local authz.
 *
 * Actors (all verified persons homed on the bootstrap branch):
 *   e2e-documents-registrar  documents.register — uploads evidence and can
 *                            never verify it (no verify capability at all).
 *   e2e-documents-verifier   documents.register + documents.verify — holds
 *                            BOTH authorities so the journey can provoke the
 *                            uploader/verifier separation-of-duties denial on
 *                            its own upload, then verify the registrar's
 *                            document independently.
 *   e2e-documents-officer    documents.classify + documents.retention —
 *                            defines classification/retention policy and
 *                            records retention decisions.
 *   (subject person)         no capabilities; the evidence subject the
 *                            registration directory offers.
 *
 * Account passwords follow the provisioning convention of the disposable
 * verification databases (same shape as the library journey accounts).
 *
 * Idempotent: once the accounts exist the script is a no-op. Re-run after
 * `php artisan migrate:fresh` to rebuild.
 *
 * Usage:
 *   DB_DATABASE=toefl_house_e2e php scripts/runtime/documents-browser-provision.php
 */

declare(strict_types=1);

use App\Modules\Identity\Models\UserAccount;
use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\BuildsActors;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class DocumentsBrowserProvisioner
{
    use BuildsActors;

    /** @return array<string, array{username: string, person_id: string}> */
    public function run(): array
    {
        if (! UserAccount::query()->where('username', 'e2e-documents-registrar')->exists()) {
            $this->personWithAuthority('e2e-documents-registrar', ['documents.register']);
            $this->personWithAuthority('e2e-documents-verifier', ['documents.register', 'documents.verify']);
            $this->personWithAuthority('e2e-documents-officer', ['documents.classify', 'documents.retention']);
            // A verified, branch-homed person with no capabilities: the
            // evidence subject the registration directory offers.
            $this->personWithAuthority('e2e-documents-subject', []);

            $this->userForActor('e2e-documents-registrar', 'e2e-documents-registrar');
            $this->userForActor('e2e-documents-verifier', 'e2e-documents-verifier');
            $this->userForActor('e2e-documents-officer', 'e2e-documents-officer');

            echo "documents browser E2E actors provisioned on DB '".config('database.connections.pgsql.database')."'\n";
        } else {
            echo "documents browser E2E accounts already present\n";
        }

        return $this->manifest();
    }

    /** @return array<string, array{username: string, person_id: string}> */
    private function manifest(): array
    {
        return UserAccount::query()
            ->whereIn('username', ['e2e-documents-registrar', 'e2e-documents-verifier', 'e2e-documents-officer'])
            ->get()
            ->mapWithKeys(static fn (UserAccount $account): array => [$account->username => [
                'username' => $account->username,
                'person_id' => $account->person_id,
            ]])
            ->all();
    }
}

$manifest = (new DocumentsBrowserProvisioner)->run();
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
