<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Access\Commands\AssignPosition;
use App\Modules\Access\Commands\DefineAccessPolicy;
use App\Modules\Access\Commands\DelegateAuthority;
use App\Modules\Access\Commands\GrantScopePermission;
use App\Modules\Access\Commands\RevokeDelegation;
use App\Modules\Access\Commands\RevokeScopePermission;
use App\Modules\Access\Commands\TransitionPositionAssignment;
use App\Modules\Access\Models\AccessPolicy;
use App\Modules\Access\Models\Delegation;
use App\Modules\Access\Models\OrgWideGrantRequest;
use App\Modules\Access\Models\Position;
use App\Modules\Access\Models\PositionAssignment;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\ScopeGrant;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccessApiController extends Controller
{
    public function workspace(): JsonResponse
    {
        $this->requireOrganizationRead('access.define_policy', 'api.access.workspace');
        $organizationIds = $this->authorizedOrganizations('access.define_policy');
        $branchIds = $this->authorizedBranches('access.assign_position');
        $people = Person::query()->where('verification_state', 'verified')->whereIn('home_branch_id', $branchIds)->orderBy('legal_name')->limit(300)->get(['id', 'legal_name', 'home_branch_id']);
        $campusIds = Campus::query()->whereIn('organization_id', $organizationIds)->pluck('id');
        $departmentIds = Department::query()->where(function ($scope) use ($branchIds, $campusIds): void {
            $scope->where(fn ($branch) => $branch->where('scope_type', 'branch')->whereIn('scope_id', $branchIds))
                ->orWhere(fn ($campus) => $campus->where('scope_type', 'campus')->whereIn('scope_id', $campusIds));
        })->pluck('id');
        $positionIds = Position::query()->whereIn('organization_id', $organizationIds)->pluck('id');

        return response()->json([
            'people' => $people,
            'organizations' => Organization::query()->whereIn('id', $organizationIds)->where('lifecycle_state', 'active')->orderBy('name')->limit(100)->get(['id', 'name']),
            'campuses' => Campus::query()->whereIn('id', $campusIds)->where('lifecycle_state', 'active')->limit(100)->get(['id', 'name']),
            'departments' => Department::query()->whereIn('id', $departmentIds)->orderBy('name')->limit(200)->get(['id', 'name', 'scope_type', 'scope_id']),
            'positions' => Position::query()->whereIn('id', $positionIds)->orderBy('name')->limit(200)->get(['id', 'name', 'organization_id']),
            'roles' => Role::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'assignments' => PositionAssignment::query()->whereIn('person_id', $people->pluck('id'))->whereIn('position_id', $positionIds)->orderByDesc('effective_from')->orderBy('id')->limit(300)->get(),
            'policies' => AccessPolicy::query()->where(function ($scope) use ($positionIds): void {
                $scope->where('binding_type', 'role')->orWhere(fn ($position) => $position->where('binding_type', 'position')->whereIn('binding_id', $positionIds));
            })->orderByDesc('effective_from')->orderBy('id')->limit(300)->get(),
            'grants' => ScopeGrant::query()->where(function ($scope) use ($organizationIds, $branchIds, $campusIds, $departmentIds): void {
                $scope->where(fn ($organization) => $organization->where('scope_type', 'organization')->whereIn('scope_id', $organizationIds))
                    ->orWhere(fn ($branch) => $branch->where('scope_type', 'branch')->whereIn('scope_id', $branchIds))
                    ->orWhere(fn ($campus) => $campus->where('scope_type', 'campus')->whereIn('scope_id', $campusIds))
                    ->orWhere(fn ($department) => $department->where('scope_type', 'department')->whereIn('scope_id', $departmentIds));
            })->orderByDesc('id')->limit(300)->get(),
            'grant_requests' => OrgWideGrantRequest::query()->whereIn('organization_id', $organizationIds)->orderByDesc('id')->limit(200)->get(),
            'delegations' => Delegation::query()->where(function ($scope) use ($people): void {
                $scope->whereIn('delegator_person_id', $people->pluck('id'))->orWhereIn('delegate_person_id', $people->pluck('id'));
            })->orderByDesc('id')->limit(300)->get(),
            'scope' => ['organization_ids' => $organizationIds, 'branch_ids' => $branchIds],
        ]);
    }

    public function assign(Request $request): JsonResponse
    {
        $input = $request->validate(['person_id' => ['required', 'string'], 'position_id' => ['required', 'string'], 'effective_from' => ['required', 'date']]);
        app(AssignPosition::class)->assign($this->actor(), $input['person_id'], $input['position_id'], CarbonImmutable::parse($input['effective_from']), $this->idempotencyKey('access.position.assign'));
        return response()->json(['status' => 'proposed'], 201);
    }

    public function assignmentTransition(Request $request, string $assignmentId, string $action): JsonResponse
    {
        $assignment = PositionAssignment::query()->findOrFail($assignmentId);
        if ($action === 'activate') app(TransitionPositionAssignment::class)->activate($this->actor(), $assignment, $this->idempotencyKey('access.position.activate'));
        else app(TransitionPositionAssignment::class)->revoke($this->actor(), $assignment, $this->idempotencyKey('access.position.revoke'));
        return response()->json(['status' => $action]);
    }

    public function bindPositionRole(Request $request): JsonResponse
    {
        $input = $request->validate(['position_id' => ['required', 'string'], 'role_id' => ['required', 'string'], 'effective_from' => ['required', 'date']]);
        app(DefineAccessPolicy::class)->bindPositionRole($this->actor(), $input['position_id'], $input['role_id'], CarbonImmutable::parse($input['effective_from']), $this->idempotencyKey('access.policy.bind'));
        return response()->json(['status' => 'published'], 201);
    }

    public function grantRolePermission(Request $request): JsonResponse
    {
        $input = $request->validate(['role_id' => ['required', 'string'], 'permission' => ['required', 'string', 'max:120'], 'effective_from' => ['required', 'date']]);
        app(DefineAccessPolicy::class)->grantRolePermission($this->actor(), $input['role_id'], $input['permission'], CarbonImmutable::parse($input['effective_from']), $this->idempotencyKey('access.policy.permission'));
        return response()->json(['status' => 'published'], 201);
    }

    public function grant(Request $request): JsonResponse
    {
        $input = $request->validate(['person_id' => ['required', 'string'], 'permission' => ['required', 'string', 'max:120'], 'scope_type' => ['required', 'string', 'in:campus,branch,department'], 'scope_id' => ['required', 'string'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'emergency' => ['sometimes', 'boolean']]);
        app(GrantScopePermission::class)->grant($this->actor(), $input['person_id'], $input['permission'], $input['scope_type'], $input['scope_id'], CarbonImmutable::parse($input['effective_from']), ! empty($input['effective_to']) ? CarbonImmutable::parse($input['effective_to']) : null, (bool) ($input['emergency'] ?? false), $this->idempotencyKey('access.grant'));
        return response()->json(['status' => 'granted'], 201);
    }

    public function requestOrgWideGrant(Request $request): JsonResponse
    {
        $input = $request->validate(['person_id' => ['required', 'string'], 'permission' => ['required', 'string', 'max:120'], 'organization_id' => ['required', 'string'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'emergency' => ['sometimes', 'boolean']]);
        app(GrantScopePermission::class)->request($this->actor(), $input['person_id'], $input['permission'], $input['organization_id'], CarbonImmutable::parse($input['effective_from']), ! empty($input['effective_to']) ? CarbonImmutable::parse($input['effective_to']) : null, (bool) ($input['emergency'] ?? false), $this->idempotencyKey('access.org_wide_grant.request'));
        return response()->json(['status' => 'requested'], 201);
    }

    public function orgWideApproval(Request $request, string $requestId, string $action): JsonResponse
    {
        $grantRequest = OrgWideGrantRequest::query()->findOrFail($requestId);
        if ($action === 'approve') app(GrantScopePermission::class)->approve($this->actor(), $grantRequest, $this->idempotencyKey('access.org_wide_grant.approve'));
        else app(GrantScopePermission::class)->execute($this->actor(), $grantRequest, $this->idempotencyKey('access.org_wide_grant.execute'));
        return response()->json(['status' => $action]);
    }

    public function revokeGrant(Request $request, string $grantId): JsonResponse
    {
        app(RevokeScopePermission::class)->revoke($this->actor(), ScopeGrant::query()->findOrFail($grantId), $this->idempotencyKey('access.revoke'));
        return response()->json(['status' => 'revoked']);
    }

    public function delegate(Request $request): JsonResponse
    {
        $input = $request->validate(['delegator_person_id' => ['required', 'string'], 'delegate_person_id' => ['required', 'string'], 'permission' => ['nullable', 'string', 'max:120'], 'scope_type' => ['nullable', 'string', 'in:campus,branch,department,organization'], 'scope_id' => ['nullable', 'string'], 'effective_from' => ['required', 'date'], 'effective_to' => ['required', 'date', 'after:effective_from'], 'reason' => ['required', 'string', 'max:1000']]);
        app(DelegateAuthority::class)->delegate($this->actor(), $input['delegator_person_id'], $input['delegate_person_id'], $input['permission'] ?: null, $input['scope_type'] ?: null, $input['scope_id'] ?: null, CarbonImmutable::parse($input['effective_from']), CarbonImmutable::parse($input['effective_to']), $input['reason'], $this->idempotencyKey('access.delegate'));
        return response()->json(['status' => 'active'], 201);
    }

    public function revokeDelegation(Request $request, string $delegationId): JsonResponse
    {
        app(RevokeDelegation::class)->revoke($this->actor(), Delegation::query()->findOrFail($delegationId), $this->idempotencyKey('access.delegate.revoke'));
        return response()->json(['status' => 'revoked']);
    }
}
