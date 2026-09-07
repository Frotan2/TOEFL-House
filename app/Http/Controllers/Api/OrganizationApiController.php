<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\Position;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use Illuminate\Http\JsonResponse;

final class OrganizationApiController extends Controller
{
    public function workspace(): JsonResponse
    {
        $this->requireOrganizationRead('organization.structure.initiate', 'api.organization.workspace');
        $organizationIds = $this->authorizedOrganizations('organization.structure.initiate');
        $branchIds = $this->authorizedBranches('organization.structure.initiate');
        $campusIds = Campus::query()->whereIn('organization_id', $organizationIds)->pluck('id');

        return response()->json([
            'organizations' => Organization::query()->whereIn('id', $organizationIds)->orderBy('name')->limit(200)->get(),
            'campuses' => Campus::query()->whereIn('id', $campusIds)->orderBy('name')->limit(200)->get(),
            'departments' => Department::query()->where(function ($scope) use ($organizationIds, $campusIds, $branchIds): void {
                $scope->where(fn ($organization) => $organization->where('scope_type', 'organization')->whereIn('scope_id', $organizationIds))
                    ->orWhere(fn ($campus) => $campus->where('scope_type', 'campus')->whereIn('scope_id', $campusIds))
                    ->orWhere(fn ($branch) => $branch->where('scope_type', 'branch')->whereIn('scope_id', $branchIds));
            })->orderBy('name')->limit(200)->get(),
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->limit(200)->get(),
            'positions' => Position::query()->whereIn('organization_id', $organizationIds)->orderBy('name')->limit(200)->get(),
            'scope' => ['organization_ids' => $organizationIds, 'branch_ids' => $branchIds],
        ]);
    }
}
