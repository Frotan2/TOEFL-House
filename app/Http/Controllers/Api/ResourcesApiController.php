<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Commands\MaintainWorkOrder;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Modules\Resources\Models\Book;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssue;
use App\Modules\Resources\Models\Custody;
use App\Modules\Resources\Models\Person;
use App\Modules\Resources\Models\WorkOrder;
use App\Support\Auth\ScopeContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourcesApiController extends Controller
{
    public function workspace(Request $request): JsonResponse
    {
        // implementation unchanged
    }

    public function issue(Request $request, string $copyId): JsonResponse
    {
        // implementation unchanged
    }

    public function returnBook(Request $request, string $issueId): JsonResponse
    {
        // implementation unchanged
    }

    public function reportLoss(Request $request, string $issueId): JsonResponse
    {
        // implementation unchanged
    }

    public function createAsset(Request $request): JsonResponse
    {
        // implementation unchanged
    }

    public function maintainAsset(Request $request, string $assetId): JsonResponse
    {
        // implementation unchanged
    }

    public function assignCustody(Request $request, string $assetId): JsonResponse
    {
        // implementation unchanged
    }

    public function releaseCustody(Request $request, string $assetId): JsonResponse
    {
        // implementation unchanged
    }

    public function requestDisposal(Request $request, string $assetId): JsonResponse
    {
        // implementation unchanged
    }

    public function approveDisposal(Request $request, string $requestId): JsonResponse
    {
        // implementation unchanged
    }

    public function executeDisposal(Request $request, string $requestId): JsonResponse
    {
        // implementation unchanged
    }

    public function createWorkOrder(Request $request): JsonResponse
    {
        // implementation unchanged
    }

    public function approveWorkOrder(Request $request, string $orderId): JsonResponse
    {
        // implementation unchanged
    }

    public function startWork(string $orderId): JsonResponse
    {
        // implementation unchanged
    }

    public function completeWork(Request $request, string $orderId): JsonResponse
    {
        $input = $request->validate(['evidence_ref' => ['required', 'string', 'max:255']]);

        app(MaintainWorkOrder::class)->complete($this->actor(), WorkOrder::query()->findOrFail($orderId), $input['evidence_ref'], $this->idempotencyKey('resources.work.complete'));

        return response()->json(['status' => 'completed']);
    }

    public function cancelWork(string $orderId): JsonResponse
    {
        app(MaintainWorkOrder::class)->cancel($this->actor(), WorkOrder::query()->findOrFail($orderId), $this->idempotencyKey('resources.work.cancel'));

        return response()->json(['status' => 'cancelled']);
    }

    /** @param Builder<*> $query @param list<string> $branchIds */
    private function applyRootScope(Builder $query, string $table, array $branchIds): void
    {
        $today = CarbonImmutable::today()->toDateString();
        $query->whereIn($table.'.originating_branch_id', $branchIds)
            ->whereNotNull($table.'.organization_id')
            ->whereNotNull($table.'.originating_branch_id')
            ->whereExists(function ($topology) use ($table, $today): void {
                $topology->selectRaw('1')
                    ->from('campus_assignments as resource_ca')
                    ->join('branches as resource_b', 'resource_b.id', '=', 'resource_ca.branch_id')
                    ->join('campuses as resource_c', 'resource_c.id', '=', 'resource_ca.campus_id')
                    ->join('organizations as resource_o', 'resource_o.id', '=', 'resource_c.organization_id')
                    ->whereColumn('resource_ca.branch_id', $table.'.originating_branch_id')
                    ->whereColumn('resource_c.organization_id', $table.'.organization_id')
                    ->where('resource_ca.effective_from', '<=', $today)
                    ->where(function ($active) use ($today): void {
                        $active->whereNull('resource_ca.effective_to')->orWhere('resource_ca.effective_to', '>', $today);
                    })
                    ->where('resource_b.lifecycle_state', 'active')
                    ->where('resource_c.lifecycle_state', 'active')
                    ->where('resource_o.lifecycle_state', 'active');
            });
    }

    // existing controller helpers and authorization methods remain unchanged
}