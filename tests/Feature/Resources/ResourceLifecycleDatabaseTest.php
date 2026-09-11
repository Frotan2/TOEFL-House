<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class ResourceLifecycleDatabaseTest extends TestCase
{
    use BuildsActors;

    public function test_database_cannot_mark_an_asset_disposed_without_a_disposal_fact(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset('2026-09-10');

        $this->expectException(QueryException::class);
        DB::table('assets')->where('id', $assetId)->update(['lifecycle_state' => 'disposed']);
    }

    public function test_database_rejects_a_disposal_before_asset_acquisition(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset('2026-09-10');

        $this->expectException(QueryException::class);
        DB::table('asset_disposals')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'disposed_on' => '2026-09-09',
            'requested_by' => 'requester-a',
            'approver_one' => 'approver-a',
            'approver_two' => 'approver-b',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_a_completed_disposal_request_without_full_provenance(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = RandomIdentifier::new();
        $assetId = $this->newAsset('2026-09-10');

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->insert([
            'id' => $requestId,
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'completed',
            'requested_by' => 'requester-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_a_disposal_without_a_matching_approved_request(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset('2026-09-10');

        $this->expectException(QueryException::class);
        DB::table('asset_disposals')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'disposed_on' => '2026-09-10',
            'requested_by' => 'requester-a',
            'approver_one' => 'approver-a',
            'approver_two' => 'approver-b',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_custody_before_asset_acquisition(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset('2026-09-10');

        $this->expectException(QueryException::class);
        DB::table('custodies')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'custodian_person_id' => 'person-a',
            'assigned_on' => '2026-09-09',
            'assigned_by' => 'actor-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_returned_issuance_without_a_valid_return_date(): void
    {
        $this->ensureBootstrapAuthority();
        $copyId = $this->newCopy('2026-09-10');

        $this->expectException(QueryException::class);
        DB::table('book_issuances')->insert([
            'id' => RandomIdentifier::new(),
            'copy_id' => $copyId,
            'borrower_person_id' => 'person-a',
            'issued_on' => '2026-09-10',
            'due_on' => '2026-09-20',
            'lifecycle_state' => 'returned',
            'issued_by' => 'actor-a',
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
            'name' => 'Lifecycle Invariant Asset',
            'category' => 'equipment',
            'location' => 'library',
            'acquired_on' => $acquiredOn,
            'lifecycle_state' => 'in_service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function newCopy(string $acquiredOn): string
    {
        $id = RandomIdentifier::new();
        DB::table('book_copies')->insert([
            'id' => $id,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'code' => 'COPY-'.RandomIdentifier::new(),
            'title' => 'Lifecycle Invariant Copy',
            'acquired_on' => $acquiredOn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
