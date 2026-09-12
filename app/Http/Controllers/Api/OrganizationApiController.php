<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\Position;
use App\Modules\Audit\AttemptedOperation;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Commands\GovernStructureChange;
use App\Modules\Organization\Domain\OrganizationLifecycle;
use App\Modules\Organization\Domain\StructureChangeDefinition;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\StructureChangeRequest;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureDecision;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\AuthorizationDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transport boundary for organization topology (ORG-OPS-01). The React
 * console reads one fail-closed management projection (all lifecycle states,
 * branch campus provenance, per-record server-authorized actions) and
 * stages changes through the four-actor governance workflow. No business
 * rule lives here: proposal validation, scope enforcement, separation of
 * duties, idempotency, concurrency and audit belong to the domain commands.
 */
final class OrganizationApiController extends Controller
{
    private const CAPABILITIES = [
        StructureDecision::CAPABILITY_INITIATE,
        StructureDecision::CAPABILITY_REVIEW,
        StructureDecision::CAPABILITY_APPROVE,
    ];

    public function workspace(): JsonResponse
    {
        $actor = $this->actor();
        $access = app(AccessDecision::class);
        $root = $this->capabilitiesOn($access, $actor, null);
        if (! $this->hasAnyGovernance($root)) {
            app(AttemptedOperation::class)->deniedByActor(
                AuthorizationDenied::forCode('api.organization_read_denied', 'an organization governance capability is required'),
                $actor,
                'api.organization.workspace',
                'console',
                'index',
            );
        }

        // Organizations across every lifecycle state the actor may govern;
        // draft roots have no scoped governors yet and resolve to root
        // (organization-wide) authority.
        $organizations = [];
        $visibleOrganizationIds = [];
        foreach (Organization::query()->orderBy('name')->limit(200)->get() as $organization) {
            $scope = new StructureScope($organization->id);
            $caps = $this->capabilitiesOn($access, $actor, $scope, true);
            // Concrete scoped authority reveals the organization. Root
            // (organization-wide/branchless) authority is NOT a wildcard
            // across trust domains: it reveals only non-active roots (drafts
            // awaiting activation), which the domain resolves to root.
            // Denied root actors never reach this point (deniedByActor never
            // returns), so root governance is established here; it only
            // reveals non-active roots that the domain maps to root scope.
            $visible = $this->hasAnyGovernance($caps)
                || $organization->lifecycle_state !== OrganizationLifecycle::STATE_ACTIVE;
            if (! $visible) {
                continue;
            }
            $visibleOrganizationIds[] = $organization->id;
            $strict = $this->capabilitiesOn($access, $actor, $scope);
            $organizations[] = [
                'id' => $organization->id,
                'name' => $organization->name,
                'lifecycle_state' => $organization->lifecycle_state,
                'available_actions' => $this->transitionActions($organization->lifecycle_state, $caps) + [
                    'create_campus' => $organization->lifecycle_state === OrganizationLifecycle::STATE_ACTIVE && $strict['initiate'],
                    'create_department' => $organization->lifecycle_state === OrganizationLifecycle::STATE_ACTIVE && $strict['initiate'],
                    'rename' => $organization->lifecycle_state === OrganizationLifecycle::STATE_DRAFT
                        ? $root['initiate']
                        : $strict['initiate'],
                ],
            ];
        }

        $campusRows = Campus::query()->whereIn('organization_id', $visibleOrganizationIds)->orderBy('name')->limit(300)->get();
        $campuses = [];
        $visibleCampusIds = [];
        foreach ($campusRows as $campus) {
            $scope = new StructureScope($campus->organization_id, $campus->id);
            $caps = $this->capabilitiesOn($access, $actor, $scope, true);
            $strict = $this->capabilitiesOn($access, $actor, $scope);
            $visibleCampusIds[] = $campus->id;
            $campuses[] = [
                'id' => $campus->id,
                'organization_id' => $campus->organization_id,
                'name' => $campus->name,
                'lifecycle_state' => $campus->lifecycle_state,
                'available_actions' => $this->transitionActions($campus->lifecycle_state, $caps) + [
                    'create_branch' => $campus->lifecycle_state === OrganizationLifecycle::STATE_ACTIVE && $strict['initiate'],
                    'create_department' => $campus->lifecycle_state === OrganizationLifecycle::STATE_ACTIVE && $strict['initiate'],
                    'rename' => $strict['initiate'],
                ],
            ];
        }

        // Branch campus provenance comes from the open campus attribution,
        // never from a column on the branch row.
        $attribution = $this->openAttribution($visibleCampusIds, $visibleOrganizationIds);
        $visibleBranchIds = array_keys($attribution);
        $branchRows = Branch::query()->whereIn('id', $visibleBranchIds === [] ? [''] : $visibleBranchIds)->orderBy('name')->limit(300)->get();
        $branches = [];
        foreach ($branchRows as $branch) {
            $attributed = $attribution[$branch->id];
            $scope = new StructureScope($attributed['organization_id'], $attributed['campus_id'], $branch->id);
            $caps = $this->capabilitiesOn($access, $actor, $scope, true);
            $strict = $this->capabilitiesOn($access, $actor, $scope);
            $branches[] = [
                'id' => $branch->id,
                'name' => $branch->name,
                'lifecycle_state' => $branch->lifecycle_state,
                'campus_id' => $attributed['campus_id'],
                'organization_id' => $attributed['organization_id'],
                'available_actions' => $this->transitionActions($branch->lifecycle_state, $caps) + [
                    'create_department' => $branch->lifecycle_state === OrganizationLifecycle::STATE_ACTIVE && $strict['initiate'],
                    'transfer' => $branch->lifecycle_state === OrganizationLifecycle::STATE_ACTIVE && $strict['initiate'],
                    'rename' => $strict['initiate'],
                ],
            ];
        }

        $departments = Department::query()
            ->where(function ($query) use ($visibleOrganizationIds, $visibleCampusIds, $visibleBranchIds): void {
                $query->where(fn ($organization) => $organization->where('scope_type', 'organization')->whereIn('scope_id', $visibleOrganizationIds === [] ? [''] : $visibleOrganizationIds))
                    ->orWhere(fn ($campus) => $campus->where('scope_type', 'campus')->whereIn('scope_id', $visibleCampusIds === [] ? [''] : $visibleCampusIds))
                    ->orWhere(fn ($branch) => $branch->where('scope_type', 'branch')->whereIn('scope_id', $visibleBranchIds === [] ? [''] : $visibleBranchIds));
            })
            ->orderBy('name')
            ->limit(400)
            ->get()
            ->map(function (Department $department) use ($access, $actor, $attribution): array {
                $scope = $department->structureScope();
                $caps = $this->capabilitiesOn($access, $actor, $scope, true);
                $strict = $this->capabilitiesOn($access, $actor, $scope);
                $campusId = $department->scope_type === 'campus' ? $department->scope_id : null;
                $branchId = $department->scope_type === 'branch' ? $department->scope_id : null;
                if ($department->scope_type === 'branch' && isset($attribution[$department->scope_id])) {
                    $campusId = $attribution[$department->scope_id]['campus_id'];
                }

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'lifecycle_state' => $department->lifecycle_state,
                    'scope_type' => $department->scope_type,
                    'scope_id' => $department->scope_id,
                    'campus_id' => $campusId,
                    'branch_id' => $branchId,
                    'organization_id' => $scope->organizationId !== '' ? $scope->organizationId : null,
                    'available_actions' => $this->transitionActions($department->lifecycle_state, $caps) + [
                        'rename' => $strict['initiate'],
                    ],
                ];
            })
            ->all();

        $changeRequests = $this->changeRequests($actor, $access);
        $people = $this->people($changeRequests, $visibleBranchIds);
        sort($visibleBranchIds);
        sort($visibleOrganizationIds);

        return response()->json([
            'organizations' => $organizations,
            'campuses' => $campuses,
            'branches' => $branches,
            'departments' => $departments,
            'change_requests' => $changeRequests,
            'people' => $people,
            'positions' => Position::query()->whereIn('organization_id', $visibleOrganizationIds === [] ? [''] : $visibleOrganizationIds)->orderBy('name')->limit(200)->get(),
            'available_actions' => [
                'create_organization' => $root['initiate'],
            ],
            'scope' => ['organization_ids' => $visibleOrganizationIds, 'branch_ids' => $visibleBranchIds],
        ]);
    }

    public function propose(Request $request): JsonResponse
    {
        $changeType = (string) $request->input('change_type', '');
        $request->validate(array_merge(['change_type' => ['required', 'string', 'in:'.implode(',', [
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION,
            StructureChangeRequest::TYPE_CREATE_CAMPUS,
            StructureChangeRequest::TYPE_CREATE_BRANCH,
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT,
            StructureChangeRequest::TYPE_RENAME,
            StructureChangeRequest::TYPE_TRANSITION,
            StructureChangeRequest::TYPE_TRANSFER,
        ])]], $this->rulesFor($changeType)));

        $outcome = app(GovernStructureChange::class)->propose(
            $this->actor(),
            $changeType,
            $request->except(['change_type', 'idempotency_key']),
            $this->stageKey('organization.structure.change.propose', $changeType, (string) $request->getContent()),
        );

        return response()->json($outcome, 201);
    }

    public function review(Request $request, string $changeId): JsonResponse
    {
        $change = StructureChangeRequest::query()->findOrFail($changeId);
        $outcome = app(GovernStructureChange::class)->review(
            $this->actor(),
            $change,
            $this->stageKey('organization.structure.change.review', $changeId),
        );

        return response()->json($outcome);
    }

    public function approve(Request $request, string $changeId): JsonResponse
    {
        $change = StructureChangeRequest::query()->findOrFail($changeId);
        $outcome = app(GovernStructureChange::class)->approve(
            $this->actor(),
            $change,
            $this->stageKey('organization.structure.change.approve', $changeId, $change->owner_one_id === null ? 'one' : 'two'),
        );

        return response()->json($outcome, $change->fresh()?->lifecycle_state === StructureChangeRequest::STATE_EXECUTED ? 201 : 200);
    }

    public function reject(Request $request, string $changeId): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $change = StructureChangeRequest::query()->findOrFail($changeId);
        $outcome = app(GovernStructureChange::class)->reject(
            $this->actor(),
            $change,
            $validated['reason'],
            $this->stageKey('organization.structure.change.reject', $changeId),
        );

        return response()->json($outcome);
    }

    public function withdraw(Request $request, string $changeId): JsonResponse
    {
        $change = StructureChangeRequest::query()->findOrFail($changeId);
        $outcome = app(GovernStructureChange::class)->withdraw(
            $this->actor(),
            $change,
            $this->stageKey('organization.structure.change.withdraw', $changeId),
        );

        return response()->json($outcome);
    }

    /**
     * Transport validation shapes. Topology semantics (active parents,
     * provenance, lifecycle transitions) are re-validated by the domain
     * descriptor and canonical commands.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $changeType): array
    {
        $name = ['required', 'string', 'min:2', 'max:255'];
        $id = ['required', 'string', 'size:36'];

        return match ($changeType) {
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION => ['name' => $name],
            StructureChangeRequest::TYPE_CREATE_CAMPUS => ['organization_id' => $id, 'name' => $name],
            StructureChangeRequest::TYPE_CREATE_BRANCH => ['campus_id' => $id, 'name' => $name, 'effective_from' => ['required', 'date']],
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT => [
                'scope_type' => ['required', 'string', 'in:organization,campus,branch'],
                'scope_id' => $id,
                'name' => $name,
            ],
            StructureChangeRequest::TYPE_RENAME => [
                'unit_type' => ['required', 'string', 'in:organization,campus,branch,department'],
                'unit_id' => $id,
                'new_name' => $name,
            ],
            StructureChangeRequest::TYPE_TRANSITION => [
                'unit_type' => ['required', 'string', 'in:organization,campus,branch,department'],
                'unit_id' => $id,
                'action' => ['required', 'string', 'in:'.implode(',', StructureChangeDefinition::TRANSITION_ACTIONS)],
            ],
            StructureChangeRequest::TYPE_TRANSFER => [
                'branch_id' => $id,
                'campus_id' => $id,
                'effective_from' => ['required', 'date'],
            ],
            default => [],
        };
    }

    /**
     * @return array{initiate: bool, review: bool, approve: bool}
     */
    private function capabilitiesOn(AccessDecision $access, Actor $actor, ?StructureScope $scope, bool $allowInactive = false): array
    {
        if ($scope !== null && $allowInactive) {
            $scope = $scope->withInactiveLifecycleAccess();
        }
        $caps = [];
        foreach (['initiate', 'review', 'approve'] as $index => $name) {
            $caps[$name] = $access->decide($actor, self::CAPABILITIES[$index], $scope)->allowed;
        }

        return $caps;
    }

    /** @param array{initiate: bool, review: bool, approve: bool} $caps */
    private function hasAnyGovernance(array $caps): bool
    {
        return $caps['initiate'] || $caps['review'] || $caps['approve'];
    }

    /** @param array{review: bool, approve: bool, reject: bool, withdraw: bool} $actions */
    private function hasAnyRequestAction(array $actions): bool
    {
        return $actions['review'] || $actions['approve'] || $actions['reject'] || $actions['withdraw'];
    }

    /**
     * @param  list<string>  $visibleCampusIds
     * @param  list<string>  $visibleOrganizationIds
     * @return array<string, array{campus_id: string, organization_id: string}>
     */
    private function openAttribution(array $visibleCampusIds, array $visibleOrganizationIds): array
    {
        $attribution = [];
        $rows = DB::table('campus_assignments')
            ->whereNull('campus_assignments.effective_to')
            ->join('campuses', 'campuses.id', '=', 'campus_assignments.campus_id')
            ->when($visibleOrganizationIds !== [], fn ($query) => $query->whereIn('campuses.organization_id', $visibleOrganizationIds))
            ->get(['campus_assignments.branch_id', 'campus_assignments.campus_id', 'campuses.organization_id']);
        foreach ($rows as $row) {
            // Fail closed on malformed, ambiguous provenance: the partial
            // unique index normally prevents two open attributions.
            if (isset($attribution[$row->branch_id])) {
                unset($attribution[$row->branch_id]);

                continue;
            }
            $attribution[$row->branch_id] = [
                'campus_id' => (string) $row->campus_id,
                'organization_id' => (string) $row->organization_id,
            ];
        }

        return $attribution;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function changeRequests(Actor $actor, AccessDecision $access): array
    {
        // All open requests are candidates (filtering by scope happens
        // below through the canonical decision point); terminal history is
        // bounded to the most recent records.
        $requests = StructureChangeRequest::query()
            ->orderByRaw("CASE WHEN lifecycle_state IN ('proposed','reviewed') THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->limit(150)
            ->get();

        $result = [];
        foreach ($requests as $request) {
            try {
                $definition = StructureChangeDefinition::fromRecord($request);
            } catch (Throwable) {
                continue;
            }
            $isOwnProposal = trim((string) $request->proposed_by) === $actor->actorId;

            $actions = $this->requestActions($actor, $access, $request, $definition);
            // Operators always see their own proposals. Every other proposal
            // is visible only through a stage capability that holds on EVERY
            // governing scope (cross-organization transfers demand both), so
            // organization-wide authority in one trust domain never reveals
            // another organization's queue.
            if (! $isOwnProposal && ! $this->hasAnyRequestAction($actions)
                && ! $this->canParticipate($access, $actor, $definition)) {
                continue;
            }

            $result[] = array_merge($request->toArray(), [
                'description' => $request->description(),
                'available_actions' => $actions,
            ]);
        }

        return $result;
    }

    /**
     * @return array{review: bool, approve: bool, reject: bool, withdraw: bool}
     */
    private function requestActions(Actor $actor, AccessDecision $access, StructureChangeRequest $request, StructureChangeDefinition $definition): array
    {
        $none = ['review' => false, 'approve' => false, 'reject' => false, 'withdraw' => false];
        if ($request->lifecycle_state === StructureChangeRequest::STATE_WITHDRAWN
            || $request->lifecycle_state === StructureChangeRequest::STATE_REJECTED
            || $request->lifecycle_state === StructureChangeRequest::STATE_EXECUTED) {
            return $none;
        }

        $allScopesAllow = function (string $capability) use ($access, $actor, $definition): bool {
            foreach ($definition->governingScopes() as $scope) {
                if (! $access->decide($actor, $capability, $scope)->allowed) {
                    return false;
                }
            }

            return true;
        };

        if ($request->lifecycle_state === StructureChangeRequest::STATE_PROPOSED) {
            $distinct = trim((string) $request->proposed_by) !== $actor->actorId;
            $canReview = $distinct && $allScopesAllow(StructureDecision::CAPABILITY_REVIEW);

            return array_merge($none, [
                'review' => $canReview,
                'reject' => $canReview,
                'withdraw' => trim((string) $request->proposed_by) === $actor->actorId,
            ]);
        }

        // Reviewed: needs two distinct owner signatures; the executing second
        // owner must be outside the initiator, reviewer and first owner.
        $distinctOwner = trim((string) $request->proposed_by) !== $actor->actorId
            && trim((string) $request->reviewed_by) !== $actor->actorId
            && ($request->owner_one_id === null || trim((string) $request->owner_one_id) !== $actor->actorId);
        $canApprove = $distinctOwner && $allScopesAllow(StructureDecision::CAPABILITY_APPROVE);

        return array_merge($none, [
            'approve' => $canApprove,
            'reject' => $canApprove,
            'withdraw' => false,
        ]);
    }

    /**
     * Whether the actor could participate in any stage of a change: one
     * governance capability must be held on EVERY governing scope (cross-org
     * transfers require authority on both structures). Null scopes (root
     * platform actions such as creating or activating a draft organization)
     * are decided as null rather than treated as wildcards.
     */
    private function canParticipate(AccessDecision $access, Actor $actor, StructureChangeDefinition $definition): bool
    {
        foreach (self::CAPABILITIES as $capability) {
            foreach ($definition->governingScopes() as $scope) {
                if (! $access->decide($actor, $capability, $scope)->allowed) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $requests
     * @param  list<string>  $visibleBranchIds
     * @return list<array{id: string, legal_name: string}>
     */
    private function people(array $requests, array $visibleBranchIds): array
    {
        $ids = [];
        foreach ($requests as $request) {
            foreach (['proposed_by', 'reviewed_by', 'owner_one_id', 'owner_two_id', 'closed_by'] as $column) {
                if (! empty($request[$column])) {
                    $ids[trim((string) $request[$column])] = true;
                }
            }
        }

        $people = Person::query()
            ->where('verification_state', Person::VERIFICATION_VERIFIED)
            ->where(function ($query) use ($ids, $visibleBranchIds): void {
                if ($ids !== []) {
                    $query->whereIn('id', array_keys($ids));
                }
                if ($visibleBranchIds !== []) {
                    $query->orWhereIn('home_branch_id', $visibleBranchIds);
                }
            })
            ->orderBy('legal_name')
            ->limit(400)
            ->get(['id', 'legal_name']);

        return $people->map(static fn (Person $person): array => ['id' => $person->id, 'legal_name' => $person->legal_name])->all();
    }

    /**
     * @param  array{initiate: bool, review: bool, approve: bool}  $caps
     * @return array<string, bool>
     */
    private function transitionActions(string $lifecycleState, array $caps): array
    {
        $actions = [];
        foreach ([
            'activate' => OrganizationLifecycle::STATE_ACTIVE,
            'suspend' => OrganizationLifecycle::STATE_SUSPENDED,
            'close' => OrganizationLifecycle::STATE_CLOSED,
            'reopen' => OrganizationLifecycle::STATE_REOPENED,
        ] as $action => $target) {
            $actions[$action] = $caps['initiate'] && OrganizationLifecycle::allowsTransition($lifecycleState, $target);
        }

        return $actions;
    }

    /**
     * Stable per-actor, per-target idempotency key so retries and lost
     * responses replay safely even when the client supplies no key; an
     * explicit Idempotency-Key header still wins.
     */
    private function stageKey(string $operation, string ...$parts): string
    {
        $supplied = (string) (request()->header('Idempotency-Key') ?? request()->input('idempotency_key', ''));
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $supplied) === 1) {
            return $supplied;
        }

        return substr($operation.':'.$this->actor()->actorId.':'.hash('sha256', implode('|', $parts)), 0, 128);
    }
}
