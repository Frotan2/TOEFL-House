<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Organization\Models\Branch;
use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Characterization evidence for the disposal-withdrawal recovery gap (F7)
 * found in the forensic audit of Library & Resources. This test pins the
 * CURRENT (defective) behavior so the defect is provable before the fix;
 * the fix commit must invert these expectations into a regression test.
 */
final class ResourcesRecoveryGapReproTest extends TestCase
{
    use BuildsActors;

    private string $branchA;

    private string $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchA = RandomIdentifier::new();
        $this->branchB = RandomIdentifier::new();
        foreach ([$this->branchA => 'Repro Branch A', $this->branchB => 'Repro Branch B'] as $id => $name) {
            Branch::query()->create(['id' => $id, 'name' => $name, 'lifecycle_state' => 'active']);
            $this->attachBranchToBootstrapOrganization($id);
        }
    }

    /**
     * F7: an abandoned (never approved) disposal request wedges the asset.
     * The pending-request guard rejects every subsequent request, and no
     * withdraw/cancel authority exists anywhere in the domain.
     */
    public function test_repro_an_abandoned_disposal_request_blocks_the_asset_with_no_withdrawal_path(): void
    {
        $manager = $this->grantedActor('repro-mgr-f7', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve']);

        $registered = app(MaintainAsset::class)->register($manager, 'REPRO-F7-1', 'Repro projector', 'electronics', 'Room 1', '2026-01-15', $this->branchA, 'repro-f7-register');
        $asset = Asset::query()->findOrFail($registered['asset_id']);

        $requested = app(DisposeAsset::class)->request($manager, $asset, 'sale', 'superseded', 'repro-f7-request');
        $this->assertSame('requested', AssetDisposalRequest::query()->findOrFail($requested['request_id'])->lifecycle_state);

        // The requester realized the request was wrong and wants to withdraw it:
        // no command, no API route, and no console route exists for that.
        $this->assertFalse(method_exists(DisposeAsset::class, 'withdraw'), 'no withdrawal authority exists on the command');
        $this->assertFalse(method_exists(DisposeAsset::class, 'cancel'), 'no cancel authority exists on the command');
        $routeNames = collect(Route::getRoutes()->getRoutesByName())->keys()->all();
        $this->assertSame([], array_values(array_filter($routeNames, static fn (string $name): bool => str_contains($name, 'disposal') && (str_contains($name, 'cancel') || str_contains($name, 'withdraw')))), 'no withdrawal route exists');

        // And re-requesting is permanently rejected while the stale row lives:
        try {
            app(DisposeAsset::class)->request($manager, $asset, 'scrap', 'corrected reason', 'repro-f7-rerequest');
            $this->fail('expected the pending-request guard to reject');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.disposal_pending', $rejection->errorCode());
        }
    }
}
