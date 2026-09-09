<?php

namespace App\Http\Controllers\Api;

use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Commands\MaintainWorkOrder;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssuance;
use App\Modules\Resources\Models\Custody;
use App\Modules\Resources\Models\WorkOrder;
use App\Modules\Resources\Models\AssetDisposal;
use App\Support\Scope\ScopeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourcesApiController extends BaseApiController
{
    public function workspace(Request $request): JsonResponse
    {
        $this->requireOrganizationRead('resources.books', 'resources.workspace.index');
        $actor = $this->actor();
        $branchIds = app(ScopeResolver::class)->branchIdsFor($actor);

        $books = BookCopy::query()->whereIn('branch_id', $branchIds)->get();
        $assets = Asset::query()->whereIn('originating_branch_id', $branchIds)->get();
        $issuances = BookIssuance::query()->whereIn('branch_id', $branchIds)->get();
        $custodies = Custody::query()->whereIn('branch_id', $branchIds)->get();
        $workOrders = WorkOrder::query()->whereIn('branch_id', $branchIds)->get();
        $disposalRequests = AssetDisposalRequest::query()->whereIn('branch_id', $branchIds)->get();
        $disposals = AssetDisposal::query()->whereIn('branch_id', $branchIds)->get();

        return response()->json([
            'assets' => $assets,
            'copies' => $books,
            'issuances' => $issuances,
            'work_orders' => $workOrders,
            'open_custodies' => $custodies->whereNull('released_on')->values(),
            'disposal_requests' => $disposalRequests,
            'disposals' => $disposals,
            'book_branches' => $books->pluck('branch_id')->unique()->values(),
            'asset_branches' => $assets->pluck('originating_branch_id')->unique()->values(),
            'work_branches' => $workOrders->pluck('branch_id')->unique()->values(),
            'borrowers' => $issuances->pluck('borrower_id')->filter()->unique()->values(),
            'custodians' => $custodies->pluck('custodian_id')->filter()->unique()->values(),
        ]);
    }

    public function issueBook(Request $request): JsonResponse
    {
        $this->requireCapability('resources.books');
        $validated = $request->validate([
            'copy_id' => ['required', 'string'],
            'borrower_id' => ['required', 'string'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
        ]);

        app(CirculateBooks::class)->issue($this->actor(), BookCopy::query()->findOrFail($validated['copy_id']), $validated, $this->idempotencyKey('resources.books.issue'));

        return response()->json(['status' => 'issued']);
    }

    public function returnBook(Request $request, string $issuanceId): JsonResponse
    {
        $this->requireCapability('resources.books');
        app(CirculateBooks::class)->return($this->actor(), BookIssuance::query()->findOrFail($issuanceId), $request->validate([
            'returned_on' => ['required', 'date'],
        ]), $this->idempotencyKey('resources.books.return'));

        return response()->json(['status' => 'returned']);
    }

    public function markBookLost(Request $request, string $issuanceId): JsonResponse
    {
        $this->requireCapability('resources.books');
        app(CirculateBooks::class)->markLost($this->actor(), BookIssuance::query()->findOrFail($issuanceId), $request->validate([
            'lost_on' => ['required', 'date'],
            'evidence_ref' => ['required', 'string'],
        ]), $this->idempotencyKey('resources.books.loss'));

        return response()->json(['status' => 'lost']);
    }

    public function assignCustody(Request $request, string $assetId): JsonResponse
    {
        $this->requireCapability('resources.asset');
        app(MaintainAsset::class)->assignCustody($this->actor(), Asset::query()->findOrFail($assetId), $request->validate([
            'custodian_id' => ['required', 'string'],
            'assigned_on' => ['required', 'date'],
        ]), $this->idempotencyKey('resources.asset.custody.assign'));

        return response()->json(['status' => 'assigned']);
    }

    public function releaseCustody(string $custodyId): JsonResponse
    {
        $this->requireCapability('resources.asset');
        app(MaintainAsset::class)->releaseCustody($this->actor(), Custody::query()->findOrFail($custodyId), $this->idempotencyKey('resources.asset.custody.release'));

        return response()->json(['status' => 'released']);
    }

    public function requestDisposal(Request $request, string $assetId): JsonResponse
    {
        $this->requireCapability('resources.dispose_request');
        $validated = $request->validate([
            'method' => ['required', 'in:sale,scrap,donation'],
            'reason' => ['required', 'string'],
        ]);
        app(DisposeAsset::class)->request($this->actor(), Asset::query()->findOrFail($assetId), $validated, $this->idempotencyKey('resources.dispose.request'));

        return response()->json(['status' => 'requested']);
    }

    public function approveDisposal(string $requestId): JsonResponse
    {
        $this->requireCapability('resources.dispose_approve');
        app(DisposeAsset::class)->approve($this->actor(), AssetDisposalRequest::query()->findOrFail($requestId), $this->idempotencyKey('resources.dispose.approve'));

        return response()->json(['status' => 'approved']);
    }

    public function executeDisposal(Request $request, string $requestId): JsonResponse
    {
        $this->requireCapability('resources.dispose_approve');
        $validated = $request->validate([
            'disposed_on' => ['required', 'date'],
        ]);
        app(DisposeAsset::class)->execute($this->actor(), AssetDisposalRequest::query()->findOrFail($requestId), $validated, $this->idempotencyKey('resources.dispose.execute'));

        return response()->json(['status' => 'completed']);
    }

    public function createWorkOrder(Request $request): JsonResponse
    {
        $this->requireCapability('facilities.work');
        $validated = $request->validate([
            'branch_id' => ['required', 'string'],
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);
        app(MaintainWorkOrder::class)->request($this->actor(), $validated, $this->idempotencyKey('resources.work.request'));

        return response()->json(['status' => 'requested']);
    }

    public function approveWork(string $orderId): JsonResponse
    {
        $this->requireCapability('facilities.work_approve');
        app(MaintainWorkOrder::class)->approve($this->actor(), WorkOrder::query()->findOrFail($orderId), $this->idempotencyKey('resources.work.approve'));

        return response()->json(['status' => 'approved']);
    }

    public function startWork(string $orderId): JsonResponse
    {
        $this->requireCapability('facilities.work');
        app(MaintainWorkOrder::class)->start($this->actor(), WorkOrder::query()->findOrFail($orderId), $this->idempotencyKey('resources.work.start'));

        return response()->json(['status' => 'started']);
    }

    public function completeWork(Request $request, string $orderId): JsonResponse
    {
        $this->requireCapability('facilities.work');
        app(MaintainWorkOrder::class)->complete($this->actor(), WorkOrder::query()->findOrFail($orderId), $request->validate([
            'completed_on' => ['required', 'date'],
            'evidence_ref' => ['required', 'string'],
        ]), $this->idempotencyKey('resources.work.complete'));

        return response()->json(['status' => 'completed']);
    }

    public function cancelWork(string $orderId): JsonResponse
    {
        $this->requireCapability('facilities.work');
        app(MaintainWorkOrder::class)->cancel($this->actor(), WorkOrder::query()->findOrFail($orderId), $this->idempotencyKey('resources.work.cancel'));

        return response()->json(['status' => 'cancelled']);
    }

    /**
     * @param Builder<*> $query
     * @param list<string> $branchIds
     */
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
                    ->where('resource_ca.starts_on', '<=', $today)
                    ->where(function ($query) use ($today): void {
                        $query->whereNull('resource_ca.ends_on')->orWhere('resource_ca.ends_on', '>=', $today);
                    });
            });
    }
}
