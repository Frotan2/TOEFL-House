<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Branch;
use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Commands\MaintainWorkOrder;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposal;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssuance;
use App\Modules\Resources\Models\Custody;
use App\Modules\Resources\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canonical JSON workspace for Library & Resources.
 *
 * This is deliberately a thin transport adapter: commands remain the only
 * mutation authority, while read-side branch scope is resolved through the
 * same capability boundary used by the legacy console.
 */
final class ResourcesApiController extends Controller
{
    public function workspace(): JsonResponse
    {
        $bookBranches = $this->authorizedBranches('resources.books');
        $assetBranches = array_values(array_unique(array_merge(
            $this->authorizedBranches('resources.asset'),
            $this->authorizedBranches('resources.dispose_request'),
            $this->authorizedBranches('resources.dispose_approve'),
        ), SORT_STRING));
        $workBranches = array_values(array_unique(array_merge(
            $this->authorizedBranches('facilities.work'),
            $this->authorizedBranches('facilities.work_approve'),
        ), SORT_STRING));

        // The workspace is a branch-scoped union of Library & Resources
        // capabilities. Requiring organization-root authority here would
        // incorrectly reject branch-scoped managers even though every query
        // below is constrained to their authorized branches. Fall back to
        // the canonical organization-read decision only when no resource
        // branch is visible at all, preserving the fail-closed/no-authority
        // response and its denial audit.
        if ($bookBranches === [] && $assetBranches === [] && $workBranches === []) {
            $this->requireOrganizationRead('resources.books', 'resources.workspace.index');
        }

        $assetQuery = Asset::query();
        $this->applyRootScope($assetQuery, 'assets', $assetBranches);
        $copyQuery = BookCopy::query();
        $this->applyRootScope($copyQuery, 'book_copies', $bookBranches);
        $workQuery = WorkOrder::query();
        $this->applyRootScope($workQuery, 'work_orders', $workBranches);

        $visibleAssetIds = (clone $assetQuery)->select('id');
        $visibleCopyIds = (clone $copyQuery)->select('id');
        $visibleWorkOrderIds = (clone $workQuery)->select('id');
        $visibleIssuanceIds = BookIssuance::query()->whereIn('copy_id', $visibleCopyIds)->select('id');

        return response()->json([
            'assets' => Asset::query()->whereIn('id', $visibleAssetIds)->orderBy('code')->limit(200)->get(),
            'copies' => BookCopy::query()->whereIn('id', $visibleCopyIds)->orderBy('code')->limit(200)->get(),
            'issuances' => BookIssuance::query()->whereIn('id', $visibleIssuanceIds)->orderByDesc('issued_on')->limit(200)->get(),
            'work_orders' => WorkOrder::query()->whereIn('id', $visibleWorkOrderIds)->orderByDesc('id')->limit(200)->get(),
            'open_custodies' => Custody::query()->whereNull('released_on')->whereIn('asset_id', $visibleAssetIds)->orderBy('asset_id')->limit(200)->get(),
            'disposal_requests' => AssetDisposalRequest::query()->whereIn('asset_id', $visibleAssetIds)->orderByDesc('id')->limit(200)->get(),
            'disposals' => AssetDisposal::query()->whereIn('asset_id', $visibleAssetIds)->orderByDesc('id')->limit(200)->get(),
            'book_branches' => Branch::query()->whereIn('id', $bookBranches)->where('lifecycle_state', 'active')->orderBy('name')->get(['id', 'name']),
            'asset_branches' => Branch::query()->whereIn('id', $assetBranches)->where('lifecycle_state', 'active')->orderBy('name')->get(['id', 'name']),
            'work_branches' => Branch::query()->whereIn('id', $workBranches)->where('lifecycle_state', 'active')->orderBy('name')->get(['id', 'name']),
            'borrowers' => Person::query()->where('verification_state', 'verified')->whereIn('home_branch_id', $bookBranches)->orderBy('legal_name')->limit(300)->get(['id', 'legal_name']),
            'custodians' => Person::query()->where('verification_state', 'verified')->whereIn('home_branch_id', $assetBranches)->orderBy('legal_name')->limit(300)->get(['id', 'legal_name']),
        ]);
    }

    public function addBookCopy(Request $request): JsonResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'acquired_on' => ['required', 'date'],
            'branch_id' => ['required', 'string'],
        ]);
        app(CirculateBooks::class)->addCopy($this->actor(), $input['code'], $input['title'], $input['acquired_on'], $input['branch_id'], $this->idempotencyKey('resources.books.add'));

        return response()->json(['status' => 'recorded'], 201);
    }

    public function issueBook(Request $request, string $copyId): JsonResponse
    {
        $input = $request->validate([
            'borrower_id' => ['required', 'string'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
        ]);
        app(CirculateBooks::class)->issue($this->actor(), BookCopy::query()->findOrFail($copyId), $input['borrower_id'], $input['issued_on'], $input['due_on'], $this->idempotencyKey('resources.issue'));

        return response()->json(['status' => 'issued'], 201);
    }

    public function returnBook(Request $request, string $issuanceId): JsonResponse
    {
        $input = $request->validate(['returned_on' => ['required', 'date']]);
        app(CirculateBooks::class)->returned($this->actor(), BookIssuance::query()->findOrFail($issuanceId), $input['returned_on'], $this->idempotencyKey('resources.return'));

        return response()->json(['status' => 'returned']);
    }

    public function reportLoss(Request $request, string $issuanceId): JsonResponse
    {
        $input = $request->validate(['loss_evidence' => ['required', 'string', 'max:255']]);
        app(CirculateBooks::class)->reportLoss($this->actor(), BookIssuance::query()->findOrFail($issuanceId), $input['loss_evidence'], $this->idempotencyKey('resources.loss'));

        return response()->json(['status' => 'loss_recorded']);
    }

    public function registerAsset(Request $request): JsonResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:64'], 'location' => ['required', 'string', 'max:255'],
            'acquired_on' => ['required', 'date'], 'branch_id' => ['required', 'string'],
        ]);
        app(MaintainAsset::class)->register($this->actor(), $input['code'], $input['name'], $input['category'], $input['location'], $input['acquired_on'], $input['branch_id'], $this->idempotencyKey('resources.asset.register'));

        return response()->json(['status' => 'recorded'], 201);
    }

    public function assignCustody(Request $request, string $assetId): JsonResponse
    {
        $input = $request->validate(['custodian_id' => ['required', 'string'], 'assigned_on' => ['required', 'date']]);
        app(MaintainAsset::class)->assignCustody($this->actor(), Asset::query()->findOrFail($assetId), $input['custodian_id'], $input['assigned_on'], $this->idempotencyKey('resources.custody.assign'));

        return response()->json(['status' => 'assigned']);
    }

    public function releaseCustody(Request $request, string $assetId): JsonResponse
    {
        $input = $request->validate(['released_on' => ['required', 'date']]);
        app(MaintainAsset::class)->releaseCustody($this->actor(), Asset::query()->findOrFail($assetId), $input['released_on'], $this->idempotencyKey('resources.custody.release'));

        return response()->json(['status' => 'released']);
    }

    public function requestDisposal(Request $request, string $assetId): JsonResponse
    {
        $input = $request->validate(['method' => ['required', 'string', 'in:sale,scrap,donation'], 'reason' => ['required', 'string', 'max:255']]);
        app(DisposeAsset::class)->request($this->actor(), Asset::query()->findOrFail($assetId), $input['method'], $input['reason'], $this->idempotencyKey('resources.disposal.request'));

        return response()->json(['status' => 'requested'], 201);
    }

    public function approveDisposal(string $requestId): JsonResponse
    {
        app(DisposeAsset::class)->approve($this->actor(), AssetDisposalRequest::query()->findOrFail($requestId), $this->idempotencyKey('resources.disposal.approve'));

        return response()->json(['status' => 'approved']);
    }

    public function executeDisposal(Request $request, string $requestId): JsonResponse
    {
        $input = $request->validate(['disposed_on' => ['required', 'date']]);
        app(DisposeAsset::class)->execute($this->actor(), AssetDisposalRequest::query()->findOrFail($requestId), $input['disposed_on'], $this->idempotencyKey('resources.asset.dispose'));

        return response()->json(['status' => 'executed']);
    }

    public function requestWork(Request $request): JsonResponse
    {
        $input = $request->validate([
            'facility_note' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:1000'], 'branch_id' => ['required', 'string'],
        ]);
        app(MaintainWorkOrder::class)->request($this->actor(), $input['facility_note'], $input['description'], $input['branch_id'], $this->idempotencyKey('resources.work.request'));

        return response()->json(['status' => 'requested'], 201);
    }

    public function approveWork(string $orderId): JsonResponse
    {
        app(MaintainWorkOrder::class)->approve($this->actor(), WorkOrder::query()->findOrFail($orderId), $this->idempotencyKey('resources.work.approve'));

        return response()->json(['status' => 'approved']);
    }

    public function startWork(string $orderId): JsonResponse
    {
        app(MaintainWorkOrder::class)->start($this->actor(), WorkOrder::query()->findOrFail($orderId), $this->idempotencyKey('resources.work.start'));

        return response()->json(['status' => 'started']);
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

    /**
     * @param Builder<*> $query
     * @param  list<string>  $branchIds
     */
    private function applyRootScope(Builder $query, string $table, array $branchIds): void
    {
        $today = app(CalendarAuthority::class)->todayAsString();
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
}
