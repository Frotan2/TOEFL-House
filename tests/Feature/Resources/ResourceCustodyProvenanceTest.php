<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Branch;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class ResourceCustodyProvenanceTest extends TestCase
{
    use BuildsActors;

    public function test_database_rejects_custody_for_a_person_from_another_branch(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset('2026-09-10');

        $otherBranchId = RandomIdentifier::new();
        Branch::query()->create([
            'id' => $otherBranchId,
            'name' => 'Other Resource Branch',
            'lifecycle_state' => 'active',
        ]);
        $this->attachBranchToBootstrapOrganization($otherBranchId);

        $personId = RandomIdentifier::new();
        Person::query()->create([
            'id' => $personId,
            'legal_name' => 'Cross Branch Custodian',
            'date_of_birth' => '1980-01-01',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'fixture-cross-branch-'.$personId,
            'identity_evidence_ref' => 'evidence/fixture/cross-branch-'.$personId,
            'verified_by' => 'fixture-verifier',
            'verified_at' => now()->toDateTimeString(),
            'home_branch_id' => $otherBranchId,
        ]);

        $this->expectException(QueryException::class);
        DB::table('custodies')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'custodian_person_id' => $personId,
            'assigned_on' => '2026-09-10',
            'assigned_by' => 'actor-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function newAsset(string $acquiredOn): string
    {
        $id = RandomIdentifier::new();
        DB::table('assets')->insert([
            'id' => $id,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'code' => 'AST-'.RandomIdentifier::new(),
            'name' => 'Custody Provenance Asset',
            'category' => 'equipment',
            'location' => 'library',
            'acquired_on' => $acquiredOn,
            'lifecycle_state' => 'in_service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
