#!/usr/bin/env php
<?php

/**
 * ORG-OPS-01 browser E2E provisioning for the DEV database.
 *
 * Creates NON-OWNER operators (no Owner role, no wildcard capability set)
 * with authority shaped exactly like the feature-suite fixtures: the test
 * traits SeedsAuthority/BuildsActors/OperatesStructure are the provisioning
 * engine, so an operator that works here works under the same canonical
 * access model the product uses — there is no parallel, script-local authz.
 *
 * Operators (all on the bootstrap organization unless noted):
 *   e2e-structure-gm       organization.structure.initiate (role -> position)
 *   e2e-structure-mgr      organization.structure.review   (org scope grant)
 *   e2e-structure-owner-1  organization.structure.approve  (org scope grant)
 *   e2e-structure-owner-2  organization.structure.approve  (org scope grant)
 *   e2e-structure-nobody   no capabilities at all (fail-closed proof)
 *
 * A second, active organization ("Cross-Org Sibling") is created through
 * the canonical commands and staffed by its OWN four operators; the
 * bootstrap operators hold no grant on it (cross-org denial proof).
 *
 * Idempotent: once the accounts exist the script is a no-op. Re-run after
 * `php artisan migrate:fresh` to rebuild.
 *
 * Usage:
 *   DB_DATABASE=toefl_house_dev php scripts/runtime/structure-browser-provision.php
 */

declare(strict_types=1);

use App\Modules\Access\Domain\AccessLifecycle;
use App\Modules\Access\Models\ScopeGrant;
use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Organization;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureDecision;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\OperatesStructure;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const STRUCTURE_E2E_PASSWORD = 'structure-e2e-password';

final class StructureBrowserProvisioner
{
    use BuildsActors;
    use OperatesStructure;

    /** @return array<string, array{username: string, person_id: string}> */
    public function run(): array
    {
        if (!UserAccount::query()->where('username', 'e2e-structure-gm')->exists()) {
            // Four distinct, non-Owner operators on the bootstrap organization.
            $this->generalManager('gm-1');
            $this->structureManager('*', 'mgr-1');
            $this->structureOwner('*', 'owner-1');
            $this->structureOwner('*', 'owner-2');
            $this->actorWithoutAnyCapability('e2e-nobody-person');

            $this->accountFor('gm-1', 'e2e-structure-gm', 'Browser GM (initiator)');
            $this->accountFor('mgr-1', 'e2e-structure-mgr', 'Browser Structure Manager (reviewer)');
            $this->accountFor('owner-1', 'e2e-structure-owner-1', 'Browser Structure Owner One');
            $this->accountFor('owner-2', 'e2e-structure-owner-2', 'Browser Structure Owner Two');
            $this->accountFor('e2e-nobody-person', 'e2e-structure-nobody', 'Browser Unauthorized Operator');

            // A genuinely separate trust domain, built through the canonical
            // commands, then staffed with its own operators. The bootstrap
            // actors are deliberately NOT granted on its scope.
            $sibling = Organization::query()->where('name', 'Cross-Org Sibling')->first();
            if ($sibling === null) {
                $decision = $this->structureDecisionForGlobalActors();
                $created = $this->createCommand()->createOrganization($decision, 'Cross-Org Sibling', RandomIdentifier::new());
                /** @var Organization $sibling */
                $sibling = Organization::query()->findOrFail($created['id']);
                $this->transitionCommand()->activate($sibling->fresh(), $decision, RandomIdentifier::new());
                $sibling = Organization::query()->findOrFail($sibling->id);
            }

            foreach ([
                'sib-init-person' => [['organization.structure.initiate'], 'e2e-sibling-gm'],
                'sib-rev-person' => [['organization.structure.review'], 'e2e-sibling-mgr'],
                'sib-o1-person' => [['organization.structure.approve'], 'e2e-sibling-owner-1'],
                'sib-o2-person' => [['organization.structure.approve'], 'e2e-sibling-owner-2'],
            ] as $personId => [$capabilities, $username]) {
                $this->personWithAuthority($personId, []);
                $this->grantScopeAuthority($personId, $capabilities, 'organization', $sibling->id);
                $this->accountFor($personId, $username, 'Browser '.$username);
            }

            echo "structure browser E2E operators provisioned on DB '".config('database.connections.pgsql.database')."'\n";
        } else {
            echo "structure browser E2E operator accounts already present\n";
        }

        $this->ensureDraftOrganization();

        return $this->manifest();
    }

    /**
     * A DRAFT organization on which the bootstrap operators ARE granted, so
     * the browser journey proves the organization activation leg through the
     * UI (a brand-new organization created in the browser intentionally has
     * no operators on it yet). Each browser run passes a unique name so the
     * activation proof is never a no-op against a prior run's history.
     */
    private function ensureDraftOrganization(): void
    {
        $draftName = getenv('STRUCTURE_E2E_DRAFT_ORG') !== false
            ? (string) getenv('STRUCTURE_E2E_DRAFT_ORG')
            : 'Browser Draft Organization';
        if (Organization::query()->where('name', $draftName)->exists()) {
            echo "draft organization \"{$draftName}\" already present\n";

            return;
        }
        // Operators already exist (initial provisioning); reconstruct the
        // command decision from their fixed ids without re-seeding people.
        $decision = new StructureDecision(
            new Actor('gm-1', 'General Manager'),
            new Actor('mgr-1', 'Structure Manager'),
            [new Actor('owner-1', 'Owner One'), new Actor('owner-2', 'Owner Two')],
        );
        $createdDraft = $this->createCommand()->createOrganization($decision, $draftName, RandomIdentifier::new());
        $draft = Organization::query()->findOrFail($createdDraft['id']);
        foreach ([
            'gm-1' => 'organization.structure.initiate',
            'mgr-1' => 'organization.structure.review',
            'owner-1' => 'organization.structure.approve',
            'owner-2' => 'organization.structure.approve',
        ] as $personId => $capability) {
            $this->scopeGrant($personId, $capability, $draft->id);
        }
        echo "draft organization \"{$draftName}\" prepared\n";
    }

    /**
     * Direct named-scope grant in the canonical access model (same record
     * shape as Tests\Concerns\SeedsAuthority), for operators provisioned in
     * an earlier process — the trait helper would otherwise re-create their
     * immutable person rows and collide.
     */
    private function scopeGrant(string $personId, string $capability, string $organizationId): void
    {
        $exists = ScopeGrant::query()
            ->where('person_id', $personId)
            ->where('permission', $capability)
            ->where('scope_type', 'organization')
            ->where('scope_id', $organizationId)
            ->exists();
        if ($exists) {
            return;
        }
        ScopeGrant::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'permission' => $capability,
            'scope_type' => 'organization',
            'scope_id' => $organizationId,
            'lifecycle_state' => AccessLifecycle::STATE_ACTIVE,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_emergency' => false,
            'review_required' => false,
            'granted_by' => $personId,
        ]);
    }

    private function accountFor(string $personId, string $username, string $legalName): void
    {
        // A real login identity, same shape as the feature suite's accounts.
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => Hash::make(STRUCTURE_E2E_PASSWORD),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
        echo sprintf("  %-24s %s\n", $username, $legalName);
    }

    /** @return array<string, array{username: string, person_id: string}> */
    private function manifest(): array
    {
        $accounts = UserAccount::query()
            ->whereIn('username', [
                'e2e-structure-gm', 'e2e-structure-mgr', 'e2e-structure-owner-1', 'e2e-structure-owner-2',
                'e2e-structure-nobody', 'e2e-sibling-gm', 'e2e-sibling-mgr', 'e2e-sibling-owner-1', 'e2e-sibling-owner-2',
            ])
            ->get()
            ->mapWithKeys(static fn (UserAccount $account): array => [$account->username => [
                'username' => $account->username,
                'person_id' => $account->person_id,
            ]])
            ->all();

        return $accounts;
    }
}

$manifest = (new StructureBrowserProvisioner())->run();
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
