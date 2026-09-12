<?php

declare(strict_types=1);

namespace Tests\Canonical\Resources;

use App\Modules\Organization\Models\Branch;
use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposal;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Modules\Resources\Models\Custody;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical asset custody and staged disposal: unique catalog codes, one
 * open custody per asset with retained transfer history, branch-bound
 * custodians, and the staged disposal authority — request, two DISTINCT
 * approver sessions, execution by the requesting session, requester-only
 * withdrawal while unapproved, and complete persisted provenance.
 */
final class AssetCustodyAndStagedDisposalTest extends CanonicalTestCase
{
    private Actor $manager;

    private Actor $approverOne;

    private Actor $approverTwo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->actorWith('canon-asset-manager', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $this->approverOne = $this->actorWith('canon-asset-approver-1', ['resources.dispose_approve']);
        $this->approverTwo = $this->actorWith('canon-asset-approver-2', ['resources.dispose_approve']);
    }

    private function registerAsset(string $key, string $acquiredOn = '2026-01-15'): Asset
    {
        $assetId = app(MaintainAsset::class)->register(
            $this->manager, 'CANON-'.RandomIdentifier::new(), 'Canonical asset', 'equipment', 'Room 1', $acquiredOn, $this->sharedBranchId(), $key
        )['asset_id'];

        return Asset::query()->findOrFail($assetId);
    }

    public function test_duplicate_asset_codes_are_refused(): void
    {
        $code = 'CANON-DUP-1';
        app(MaintainAsset::class)->register($this->manager, $code, 'First', 'equipment', 'Room 1', '2026-01-15', $this->sharedBranchId(), 'canon-asset-dup-1');

        try {
            app(MaintainAsset::class)->register($this->manager, $code, 'Second', 'equipment', 'Room 2', '2026-01-16', $this->sharedBranchId(), 'canon-asset-dup-2');
            $this->fail('asset codes are unique');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.asset_code_exists', $rejection->errorCode());
        }

        $this->assertSame(1, Asset::query()->where('code', $code)->count());
    }

    public function test_custody_transfers_retain_history_and_exactly_one_open_row(): void
    {
        $asset = $this->registerAsset('canon-asset-custody-1');
        $first = $this->personWithAuthority('canon-custodian-1', [], $this->sharedBranchId());
        $second = $this->personWithAuthority('canon-custodian-2', [], $this->sharedBranchId());

        app(MaintainAsset::class)->assignCustody($this->manager, $asset, $first->id, '2026-02-01', 'canon-custody-assign-1');
        app(MaintainAsset::class)->assignCustody($this->manager, $asset, $second->id, '2026-03-01', 'canon-custody-assign-2');

        $this->assertDatabaseHas('custodies', ['asset_id' => $asset->id, 'custodian_person_id' => $first->id, 'assigned_on' => '2026-02-01', 'released_on' => '2026-03-01']);
        $this->assertSame(1, Custody::query()->where('asset_id', $asset->id)->whereNull('released_on')->count());

        app(MaintainAsset::class)->releaseCustody($this->manager, $asset, '2026-03-15', 'canon-custody-release-1');
        $this->assertSame(0, Custody::query()->where('asset_id', $asset->id)->whereNull('released_on')->count());
        $this->assertSame(2, Custody::query()->where('asset_id', $asset->id)->count(), 'custody history is retained');
    }

    public function test_the_database_rejects_a_second_open_custody_row(): void
    {
        $asset = $this->registerAsset('canon-asset-custody-5');
        $custodian = $this->personWithAuthority('canon-custodian-6', [], $this->sharedBranchId());
        app(MaintainAsset::class)->assignCustody($this->manager, $asset, $custodian->id, '2026-02-01', 'canon-custody-assign-9');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('custodies_one_open_per_asset');
        DB::table('custodies')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $asset->id,
            'custodian_person_id' => $custodian->id,
            'assigned_on' => '2026-03-02',
            'assigned_by' => 'canon-asset-manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_custody_rules_bind_the_custodian_to_the_asset_branch_and_time(): void
    {
        $asset = $this->registerAsset('canon-asset-custody-2', '2026-02-01');
        $sameBranch = $this->personWithAuthority('canon-custodian-3', [], $this->sharedBranchId());

        $otherBranch = RandomIdentifier::new();
        Branch::query()->create(['id' => $otherBranch, 'name' => 'Canonical other asset branch', 'lifecycle_state' => 'active']);
        $this->attachBranchToBootstrapOrganization($otherBranch);
        $otherBranchPerson = $this->personWithAuthority('canon-custodian-4', [], $otherBranch);

        try {
            app(MaintainAsset::class)->assignCustody($this->manager, $asset, $otherBranchPerson->id, '2026-02-10', 'canon-custody-assign-3');
            $this->fail('custody custodians must belong to the asset branch');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.custodian_branch_mismatch', $rejection->errorCode());
        }

        try {
            app(MaintainAsset::class)->assignCustody($this->manager, $asset, $sameBranch->id, '2026-01-31', 'canon-custody-assign-4');
            $this->fail('custody cannot begin before acquisition');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.custody_assigned_on', $rejection->errorCode());
        }

        app(MaintainAsset::class)->assignCustody($this->manager, $asset, $sameBranch->id, '2026-02-10', 'canon-custody-assign-5');

        try {
            app(MaintainAsset::class)->assignCustody($this->manager, $asset, $sameBranch->id, '2026-02-20', 'canon-custody-assign-6');
            $this->fail('the same custodian cannot hold the asset twice');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.custody_same_custodian', $rejection->errorCode());
        }

        $this->assertSame(1, Custody::query()->where('asset_id', $asset->id)->count());
    }

    public function test_staged_disposal_persists_the_complete_provenance_chain(): void
    {
        $asset = $this->registerAsset('canon-asset-disposal-1');
        $custodian = $this->personWithAuthority('canon-custodian-5', [], $this->sharedBranchId());
        app(MaintainAsset::class)->assignCustody($this->manager, $asset, $custodian->id, '2026-02-01', 'canon-custody-assign-7');

        $requested = app(DisposeAsset::class)->request($this->manager, $asset, 'sale', 'superseded by newer model', 'canon-disposal-request-1');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);
        $this->assertDatabaseHas('asset_disposal_requests', ['id' => $request->id, 'lifecycle_state' => 'requested', 'requested_by' => 'canon-asset-manager']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $requested['correlation_id']]);

        $firstSignature = app(DisposeAsset::class)->approve($this->approverOne, $request, 'canon-disposal-approve-1');
        $this->assertSame('requested', $firstSignature['lifecycle_state'], 'the request stays requested until the second signature');
        $request->refresh();
        $this->assertSame('canon-asset-approver-1', trim((string) $request->approver_one_id));

        $secondSignature = app(DisposeAsset::class)->approve($this->approverTwo, $request, 'canon-disposal-approve-2');
        $this->assertSame('approved', $secondSignature['lifecycle_state']);

        $executed = app(DisposeAsset::class)->execute($this->manager, $request, '2026-04-01', 'canon-disposal-execute-1');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'lifecycle_state' => 'disposed']);
        $this->assertDatabaseHas('asset_disposals', [
            'id' => $executed['disposal_id'], 'asset_id' => $asset->id, 'method' => 'sale',
            'disposed_on' => '2026-04-01', 'requested_by' => 'canon-asset-manager',
            'approver_one' => 'canon-asset-approver-1', 'approver_two' => 'canon-asset-approver-2',
        ]);
        $this->assertDatabaseHas('asset_disposal_requests', [
            'id' => $request->id, 'lifecycle_state' => 'completed',
            'executed_by' => 'canon-asset-manager', 'disposal_id' => $executed['disposal_id'],
        ]);
        // Disposal execution closes the open custody at the disposal date.
        $this->assertDatabaseHas('custodies', ['asset_id' => $asset->id, 'custodian_person_id' => $custodian->id, 'released_on' => '2026-04-01']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $executed['correlation_id']]);
        $this->assertSame(1, AssetDisposal::query()->where('asset_id', $asset->id)->count());

        // Disposed assets leave the operational lifecycle for good.
        try {
            app(MaintainAsset::class)->assignCustody($this->manager, $asset->refresh(), $custodian->id, '2026-04-02', 'canon-custody-assign-8');
            $this->fail('custody attaches only to in-service assets');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.asset_not_in_service', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::table('assets')->where('id', $asset->id)->update(['lifecycle_state' => 'in_service']);
    }

    public function test_disposal_independence_and_executor_rules_are_denied_and_audited(): void
    {
        $asset = $this->registerAsset('canon-asset-disposal-2');
        $requested = app(DisposeAsset::class)->request($this->manager, $asset, 'scrap', 'worn out', 'canon-disposal-request-2');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);

        try {
            app(DisposeAsset::class)->approve($this->manager, $request, 'canon-disposal-approve-self');
            $this->fail('the requester may not approve their own request');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.disposal_not_independent', $denial->errorCode());
        }

        app(DisposeAsset::class)->approve($this->approverOne, $request, 'canon-disposal-approve-3');
        try {
            app(DisposeAsset::class)->approve($this->approverOne, $request, 'canon-disposal-approve-4');
            $this->fail('one approver cannot sign twice');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.disposal_single_actor', $denial->errorCode());
        }

        try {
            app(DisposeAsset::class)->execute($this->manager, $request, '2026-04-01', 'canon-disposal-execute-early');
            $this->fail('execution requires two signatures');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_request_state', $rejection->errorCode());
        }

        app(DisposeAsset::class)->approve($this->approverTwo, $request, 'canon-disposal-approve-5');

        $foreignExecutor = $this->actorWith('canon-asset-executor-x', ['resources.dispose_request']);
        try {
            app(DisposeAsset::class)->execute($foreignExecutor, $request, '2026-04-01', 'canon-disposal-execute-foreign');
            $this->fail('only the requesting session executes');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.disposal_executor', $denial->errorCode());
        }

        $denialEvent = DB::table('audit_events')
            ->where('operation', 'resources.asset.dispose.denied')
            ->where('actor_id', 'canon-asset-executor-x')
            ->first();
        $this->assertNotNull($denialEvent);
        $this->assertSame(0, DB::table('domain_events')->where('audit_event_id', $denialEvent->id)->count());
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'lifecycle_state' => 'in_service']);

        try {
            app(DisposeAsset::class)->execute($this->manager, $request, '2025-12-31', 'canon-disposal-execute-past');
            $this->fail('disposal cannot precede acquisition');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_date', $rejection->errorCode());
        }
    }

    public function test_requester_withdrawal_frees_the_asset_and_is_terminal(): void
    {
        $asset = $this->registerAsset('canon-asset-disposal-3');
        $requested = app(DisposeAsset::class)->request($this->manager, $asset, 'donation', 'wrong method', 'canon-disposal-request-3');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);

        $withdrawn = app(DisposeAsset::class)->withdraw($this->manager, $request, 'canon-disposal-withdraw-1');
        $this->assertSame('withdrawn', $withdrawn['lifecycle_state']);
        $this->assertDatabaseHas('asset_disposal_requests', ['id' => $request->id, 'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'canon-asset-manager']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $withdrawn['correlation_id']]);

        // The asset is operable again through a corrected request.
        $corrected = app(DisposeAsset::class)->request($this->manager, $asset, 'scrap', 'corrected method', 'canon-disposal-request-4');
        $this->assertDatabaseHas('asset_disposal_requests', ['id' => $corrected['request_id'], 'lifecycle_state' => 'requested']);

        // Withdrawn history is terminal at the database boundary.
        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $request->id)->update(['lifecycle_state' => 'requested']);
    }

    public function test_the_database_rejects_a_second_active_disposal_request(): void
    {
        $asset = $this->registerAsset('canon-asset-disposal-4');
        app(DisposeAsset::class)->request($this->manager, $asset, 'sale', 'first', 'canon-disposal-request-5');

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $asset->id,
            'method' => 'scrap',
            'reason' => 'second',
            'lifecycle_state' => 'requested',
            'requested_by' => 'canon-asset-manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
