<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class ResourceProvenanceDatabaseTest extends TestCase
{
    use BuildsActors;

    public function test_requested_work_order_may_be_cancelled_without_an_approval_actor(): void
    {
        $this->ensureBootstrapAuthority();
        $id = RandomIdentifier::new();
        DB::table('work_orders')->insert([
            'id' => $id,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'facility_note' => 'Library',
            'description' => 'Cancel before approval',
            'lifecycle_state' => 'requested',
            'requested_by' => 'requester-a',
            'approved_by' => null,
            'evidence_ref' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('work_orders')->where('id', $id)->update(['lifecycle_state' => 'cancelled']);
        $this->assertSame('cancelled', DB::table('work_orders')->where('id', $id)->value('lifecycle_state'));
    }

    public function test_database_rejects_approved_disposal_request_without_two_distinct_approvers(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset();

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'approved',
            'requested_by' => 'requester-a',
            'approver_one_id' => 'approver-a',
            'approver_two_id' => null,
            'executed_by' => null,
            'disposal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_completed_disposal_request_without_execution_provenance(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset();

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'completed',
            'requested_by' => 'requester-a',
            'approver_one_id' => 'approver-a',
            'approver_two_id' => 'approver-b',
            'executed_by' => null,
            'disposal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_disposal_fact_without_matching_request_provenance(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset();

        DB::table('asset_disposal_requests')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'approved',
            'requested_by' => 'requester-a',
            'approver_one_id' => 'approver-a',
            'approver_two_id' => 'approver-b',
            'executed_by' => null,
            'disposal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('asset_disposals')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'sale',
            'reason' => 'different request',
            'disposed_on' => '2026-09-10',
            'requested_by' => 'requester-a',
            'approver_one' => 'approver-a',
            'approver_two' => 'approver-b',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function newAsset(): string
    {
        $id = RandomIdentifier::new();
        DB::table('assets')->insert([
            'id' => $id,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'code' => 'AST-'.RandomIdentifier::new(),
            'name' => 'Provenance Test Asset',
            'category' => 'equipment',
            'location' => 'library',
            'acquired_on' => '2026-09-10',
            'lifecycle_state' => 'in_service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
