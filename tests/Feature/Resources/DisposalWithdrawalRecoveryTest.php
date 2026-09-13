<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Regression coverage for disposal-request withdrawal (000209): the
 * requesting session may withdraw while the request is still 'requested';
 * withdrawal is terminal, is audited, frees the asset for a corrected
 * request, and is enforced at the PostgreSQL boundary. Before this recovery
 * path existed, an abandoned request wedged the asset forever because the
 * one-active-per-asset guard rejected every new request and both history
 * guards prohibit deletion.
 */
final class DisposalWithdrawalRecoveryTest extends TestCase
{
    use BuildsActors;

    private function registeredAsset(string $managerId, string $registerKey): Asset
    {
        $manager = $this->actorWithStructureCapabilities($managerId, ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $registered = app(MaintainAsset::class)->register($manager, 'WD-'.RandomIdentifier::new(), 'Withdrawal asset', 'equipment', 'library', '2026-01-15', $this->bootstrapBranchId, $registerKey);

        return Asset::query()->findOrFail($registered['asset_id']);
    }

    private function rawAsset(): string
    {
        $assetId = RandomIdentifier::new();
        DB::table('assets')->insert([
            'id' => $assetId,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'code' => 'AST-'.RandomIdentifier::new(),
            'name' => 'Withdrawal Guard Asset',
            'category' => 'equipment',
            'location' => 'library',
            'acquired_on' => '2026-09-01',
            'lifecycle_state' => 'in_service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $assetId;
    }

    private function rawRequest(string $assetId, string $requestedBy = 'requester-a'): string
    {
        $requestId = RandomIdentifier::new();
        DB::table('asset_disposal_requests')->insert([
            'id' => $requestId,
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'requested',
            'requested_by' => $requestedBy,
            'approver_one_id' => null,
            'approver_two_id' => null,
            'executed_by' => null,
            'disposal_id' => null,
            'withdrawn_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $requestId;
    }

    public function test_requester_withdrawal_frees_the_asset_for_a_corrected_request(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->actorWithStructureCapabilities('wd-requester', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $asset = $this->registeredAsset('wd-requester', 'wd-register-1');

        $requested = app(DisposeAsset::class)->request($manager, $asset, 'sale', 'wrong method chosen', 'wd-request-1');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);

        $withdrawn = app(DisposeAsset::class)->withdraw($manager, $request, 'wd-withdraw-1');

        $this->assertSame('withdrawn', $withdrawn['lifecycle_state']);
        $this->assertDatabaseHas('asset_disposal_requests', [
            'id' => $request->id, 'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'wd-requester',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'resources.disposal.withdraw',
            'actor_id' => 'wd-requester',
            'target_type' => 'asset_disposal_request',
            'target_id' => $request->id,
        ]);

        // The asset is operable again: a corrected request succeeds, and the
        // one-active guard immediately re-engages for the fresh request.
        $corrected = app(DisposeAsset::class)->request($manager, $asset, 'scrap', 'corrected method', 'wd-request-2');
        $this->assertDatabaseHas('asset_disposal_requests', ['id' => $corrected['request_id'], 'lifecycle_state' => 'requested']);

        try {
            app(DisposeAsset::class)->request($manager, $asset, 'donation', 'another attempt', 'wd-request-3');
            $this->fail('only one active disposal request per asset');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_pending', $rejection->errorCode());
        }
    }

    public function test_a_different_session_cannot_withdraw_and_the_denial_is_audited(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->actorWithStructureCapabilities('wd-requester-b', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $outsider = $this->actorWithStructureCapabilities('wd-outsider', ['resources.dispose_request']);
        $asset = $this->registeredAsset('wd-requester-b', 'wd-register-b');
        $requested = app(DisposeAsset::class)->request($manager, $asset, 'sale', 'superseded', 'wd-request-b');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);

        try {
            app(DisposeAsset::class)->withdraw($outsider, $request, 'wd-withdraw-b');
            $this->fail('only the requesting session may withdraw');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.disposal_withdrawer', $denial->errorCode());
        }

        $this->assertDatabaseHas('audit_events', [
            'operation' => 'resources.disposal.withdraw.denied',
            'actor_id' => 'wd-outsider',
            'target_id' => $request->id,
        ]);
        $this->assertSame('requested', AssetDisposalRequest::query()->findOrFail($request->id)->lifecycle_state);
    }

    public function test_an_approved_request_can_no_longer_be_withdrawn_and_still_executes(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->actorWithStructureCapabilities('wd-requester-c', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $approverOne = $this->actorWithStructureCapabilities('wd-approver-c1', ['resources.dispose_approve']);
        $approverTwo = $this->actorWithStructureCapabilities('wd-approver-c2', ['resources.dispose_approve']);
        $asset = $this->registeredAsset('wd-requester-c', 'wd-register-c');
        $requested = app(DisposeAsset::class)->request($manager, $asset, 'scrap', 'end of life', 'wd-request-c');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);

        app(DisposeAsset::class)->approve($approverOne, $request, 'wd-approve-c1');
        app(DisposeAsset::class)->approve($approverTwo, $request, 'wd-approve-c2');
        $request->refresh();
        $this->assertSame('approved', $request->lifecycle_state);

        try {
            app(DisposeAsset::class)->withdraw($manager, $request, 'wd-withdraw-c');
            $this->fail('an approved request can only be executed');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_request_state', $rejection->errorCode());
        }

        $executed = app(DisposeAsset::class)->execute($manager, $request, '2026-04-01', 'wd-execute-c');
        $this->assertSame($asset->id, $executed['asset_id']);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'lifecycle_state' => 'disposed']);
    }

    public function test_withdrawn_requests_are_terminal_against_every_transition(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->actorWithStructureCapabilities('wd-requester-d', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $approver = $this->actorWithStructureCapabilities('wd-approver-d', ['resources.dispose_approve']);
        $asset = $this->registeredAsset('wd-requester-d', 'wd-register-d');
        $requested = app(DisposeAsset::class)->request($manager, $asset, 'donation', 'no longer needed', 'wd-request-d');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);
        app(DisposeAsset::class)->withdraw($manager, $request, 'wd-withdraw-d');

        try {
            app(DisposeAsset::class)->withdraw($manager, $request, 'wd-withdraw-d2');
            $this->fail('withdrawal is terminal');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_request_state', $rejection->errorCode());
        }

        try {
            app(DisposeAsset::class)->approve($approver, $request, 'wd-approve-d');
            $this->fail('a withdrawn request cannot be approved');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_request_state', $rejection->errorCode());
        }

        try {
            app(DisposeAsset::class)->execute($manager, $request, '2026-04-01', 'wd-execute-d');
            $this->fail('a withdrawn request cannot be executed');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_request_state', $rejection->errorCode());
        }

        $this->assertDatabaseHas('asset_disposal_requests', ['id' => $request->id, 'lifecycle_state' => 'withdrawn']);
    }

    public function test_withdrawal_after_the_first_signature_retains_the_recorded_signature(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->actorWithStructureCapabilities('wd-requester-e', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $approverOne = $this->actorWithStructureCapabilities('wd-approver-e1', ['resources.dispose_approve']);
        $asset = $this->registeredAsset('wd-requester-e', 'wd-register-e');
        $requested = app(DisposeAsset::class)->request($manager, $asset, 'sale', 'changing plans', 'wd-request-e');
        $request = AssetDisposalRequest::query()->findOrFail($requested['request_id']);

        app(DisposeAsset::class)->approve($approverOne, $request, 'wd-approve-e1');
        app(DisposeAsset::class)->withdraw($manager, $request, 'wd-withdraw-e');

        $this->assertDatabaseHas('asset_disposal_requests', [
            'id' => $request->id,
            'lifecycle_state' => 'withdrawn',
            'approver_one_id' => 'wd-approver-e1',
            'withdrawn_by' => 'wd-requester-e',
        ]);
    }

    public function test_withdrawal_replays_idempotently_and_rejects_a_conflicting_payload(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->actorWithStructureCapabilities('wd-requester-f', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);
        $assetOne = $this->registeredAsset('wd-requester-f', 'wd-register-f1');
        $assetTwo = $this->registeredAsset('wd-requester-f', 'wd-register-f2');
        $first = AssetDisposalRequest::query()->findOrFail(app(DisposeAsset::class)->request($manager, $assetOne, 'scrap', 'first', 'wd-request-f1')['request_id']);
        $second = AssetDisposalRequest::query()->findOrFail(app(DisposeAsset::class)->request($manager, $assetTwo, 'scrap', 'second', 'wd-request-f2')['request_id']);

        $withdrawn = app(DisposeAsset::class)->withdraw($manager, $first, 'wd-withdraw-f');
        $replayed = app(DisposeAsset::class)->withdraw($manager, $first, 'wd-withdraw-f');
        $this->assertSame($withdrawn['correlation_id'], $replayed['correlation_id'], 'an identical replay returns the recorded outcome');

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('idempotency key reused with a different payload');
        app(DisposeAsset::class)->withdraw($manager, $second, 'wd-withdraw-f');
    }

    public function test_database_rejects_withdrawal_without_a_recorded_withdrawer(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = $this->rawRequest($this->rawAsset());

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $requestId)->update(['lifecycle_state' => 'withdrawn', 'updated_at' => now()]);
    }

    public function test_database_rejects_withdrawal_recorded_by_another_session(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = $this->rawRequest($this->rawAsset());

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $requestId)->update([
            'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'someone-else', 'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_reviving_a_withdrawn_request(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = $this->rawRequest($this->rawAsset());
        DB::table('asset_disposal_requests')->where('id', $requestId)->update([
            'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'requester-a', 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $requestId)->update(['lifecycle_state' => 'requested', 'updated_at' => now()]);
    }

    public function test_database_rejects_rewriting_the_recorded_withdrawal(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = $this->rawRequest($this->rawAsset());
        DB::table('asset_disposal_requests')->where('id', $requestId)->update([
            'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'requester-a', 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $requestId)->update(['withdrawn_by' => 'requester-b', 'updated_at' => now()]);
    }

    public function test_database_rejects_deleting_a_withdrawn_request(): void
    {
        $this->ensureBootstrapAuthority();
        $requestId = $this->rawRequest($this->rawAsset());
        DB::table('asset_disposal_requests')->where('id', $requestId)->update([
            'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'requester-a', 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->where('id', $requestId)->delete();
    }

    public function test_database_rejects_a_request_born_withdrawn(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = $this->rawAsset();

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->insert([
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'withdrawn',
            'requested_by' => 'requester-a',
            'withdrawn_by' => 'requester-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
