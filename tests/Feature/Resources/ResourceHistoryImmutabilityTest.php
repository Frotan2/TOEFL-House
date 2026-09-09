<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class ResourceHistoryImmutabilityTest extends TestCase
{
    use BuildsActors;

    public function test_database_cannot_delete_custody_history(): void
    {
        $this->ensureBootstrapAuthority();
        $custodian = $this->personWithAuthority('custodian-history-1', []);
        $assetId = $this->newAsset('2026-09-10');
        $custodyId = RandomIdentifier::new();

        DB::table('custodies')->insert([
            'id' => $custodyId,
            'asset_id' => $assetId,
            'custodian_person_id' => $custodian->id,
            'assigned_on' => '2026-09-10',
            'assigned_by' => 'actor-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('custodies')->where('id', $custodyId)->delete();
    }

    public function test_database_cannot_delete_disposal_request_history(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = RandomIdentifier::new();
        $assetId = $this->newAsset('2026-09-10');

        DB::table('asset_disposal_requests')->insert([
            'id' => $requestId,
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'requested',
            'requested_by' => 'requester-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $requestId)->delete();
    }

    public function test_database_cannot_delete_or_rewrite_disposal_fact(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->newAsset('2026-09-10');
        $requestId = RandomIdentifier::new();
        $disposalId = RandomIdentifier::new();

        DB::table('asset_disposal_requests')->insert([
            'id' => $requestId,
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'approved',
            'requested_by' => 'requester-a',
            'approver_one_id' => 'approver-a',
            'approver_two_id' => 'approver-b',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_disposals')->insert([
            'id' => $disposalId,
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

        try {
            DB::table('asset_disposals')->where('id', $disposalId)->update(['disposed_on' => '2026-09-11']);
            $this->fail('Disposal facts must be immutable.');
        } catch (QueryException) {
            // Expected database invariant failure.
        }

        $this->expectException(QueryException::class);
        DB::table('asset_disposals')->where('id', $disposalId)->delete();
    }

    private function newAsset(string $acquiredOn): string
    {
        $id = RandomIdentifier::new();
        DB::table('assets')->insert([
            'id' => $id,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'code' => 'AST-'.RandomIdentifier::new(),
            'name' => 'History Invariant Asset',
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
