<?php

declare(strict_types=1);

namespace App\Modules\WorkManagement\Queries;

use App\Modules\Hr\Domain\EmploymentLifecycle;
use App\Modules\Hr\Models\Employment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use App\Modules\WorkManagement\Models\WorkItem;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\ActorBranches;
use App\Support\Authorization\StructureScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Modules\Calendar\CalendarAuthority;

/** Fail-closed operational projection; discovery never authorizes source commands. */
final class WorkItemQuery
{
    /** @return list<array<string, mixed>> */
    public function forActor(Actor $actor): array
    {
        $employment = Employment::query()->where('person_id', $actor->actorId)->orderByDesc('created_at')->orderByDesc('id')->first();
        if ($employment === null || $employment->lifecycle_state !== EmploymentLifecycle::STATE_ACTIVE) {
            return [];
        }
        $candidateBranches = array_values(app(ActorBranches::class)->visibleBranchIds($actor));
        $branches = $this->authorizedBranches($actor, $candidateBranches);
        $branchOrganizations = $this->branchOrganizations($branches);
        $organizationIds = $this->authorizedOrganizations($actor);
        $queueMemberships = app(QueueMembershipQuery::class);
        $organizationQueues = [];
        foreach ($organizationIds as $organizationId) {
            foreach ($queueMemberships->activeQueueKeys($actor, null, $organizationId) as $queueKey) {
                $organizationQueues[] = ['organization_id' => $organizationId, 'queue_key' => $queueKey];
            }
        }
        $branchQueues = [];
        foreach ($branches as $branchId) {
            $branch = Branch::query()->whereKey($branchId)->first();
            $organizationId = $branch === null ? '' : trim((string) $branch->structureScope()->organizationId);
            if ($organizationId === '') {
                continue;
            }
            foreach ($queueMemberships->activeQueueKeys($actor, $branchId, $organizationId) as $queueKey) {
                $branchQueues[] = ['branch_id' => $branchId, 'queue_key' => $queueKey];
            }
        }
        $query = WorkItem::query()
            ->where(function ($assignment) use ($actor, $organizationQueues, $branchQueues): void {
                $assignment->where('assigned_to', $actor->actorId)->orWhere(function ($queue) use ($organizationQueues, $branchQueues): void {
                    $queue->whereNull('assigned_to')->where(function ($membership) use ($organizationQueues, $branchQueues): void {
                        if ($organizationQueues === [] && $branchQueues === []) {
                            $membership->whereRaw('1 = 0');

                            return;
                        }
                        foreach ($organizationQueues as $scope) {
                            $membership->orWhere(fn ($organization) => $organization->where('organization_id', $scope['organization_id'])->where('queue_key', $scope['queue_key'])->whereNull('branch_id'));
                        }
                        foreach ($branchQueues as $scope) {
                            $membership->orWhere(fn ($branch) => $branch->where('branch_id', $scope['branch_id'])->where('queue_key', $scope['queue_key']));
                        }
                    });
                });
            })
            ->whereIn('lifecycle_state', ['open', 'claimed', 'in_progress'])
            ->orderBy('priority')->orderBy('due_at');
        $query->where(function ($scope) use ($organizationIds, $branchOrganizations): void {
            $hasCondition = false;
            if ($organizationIds !== []) {
                $scope->where(fn ($organization) => $organization->whereNull('branch_id')->whereIn('organization_id', $organizationIds));
                $hasCondition = true;
            }
            foreach ($branchOrganizations as $branchId => $organizationId) {
                $method = $hasCondition ? 'orWhere' : 'where';
                $scope->{$method}(fn ($branch) => $branch->where('branch_id', $branchId)->where('organization_id', $organizationId));
                $hasCondition = true;
            }
            if (! $hasCondition) {
                $scope->whereRaw('1 = 0');
            }
        });

        $now = app(CalendarAuthority::class)->nowUtc();

        return array_values($query->limit(100)->get([
            'id', 'kind', 'title', 'source_type', 'source_id', 'action_key', 'organization_id', 'branch_id', 'priority', 'due_at', 'lifecycle_state', 'sla_policy_key', 'sla_state', 'escalation_level', 'last_escalated_at',
        ])->map(static function (WorkItem $item) use ($now): array {
            $slaState = (string) $item->sla_state;
            if ($item->due_at !== null && ! in_array($item->lifecycle_state, ['completed', 'cancelled', 'expired'], true)) {
                $seconds = $now->diffInSeconds($item->due_at, false);
                $slaState = $seconds < 0 ? 'breached' : ($seconds <= 86_400 ? 'at_risk' : 'on_track');
            }

            return [
                'organization_id' => $item->organization_id, 'branch_id' => $item->branch_id,
                'id' => (string) $item->id, 'kind' => (string) $item->kind,
                'source_type' => (string) $item->source_type, 'source_id' => (string) $item->source_id,
                'title' => (string) $item->title, 'status' => (string) $item->lifecycle_state,
                'due_at' => $item->due_at?->toIso8601String(), 'sla_state' => $slaState,
                'sla_policy_key' => $item->sla_policy_key, 'escalation_level' => (int) $item->escalation_level,
                'last_escalated_at' => $item->last_escalated_at?->toIso8601String(),
                'route' => '/workspace?work_item_id='.(string) $item->id, 'action_key' => (string) $item->action_key,
            ];
        })->values()->all());
    }

    /**
     * @param  list<string>  $branchIds
     * @return array<string, string>
     */
    private function branchOrganizations(array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }
        $organizations = [];
        foreach (Branch::query()->whereIn('id', $branchIds)->get() as $branch) {
            try {
                $scope = $branch->structureScope();
            } catch (ModelNotFoundException) {
                continue;
            }
            $organizationId = trim((string) $scope->organizationId);
            if ($organizationId !== '') {
                $organizations[(string) $branch->id] = $organizationId;
            }
        }

        return $organizations;
    }

    /** @return list<string> */
    private function authorizedOrganizations(Actor $actor): array
    {
        $decision = app(AccessDecision::class);
        $authorized = [];
        foreach (Organization::query()->where('lifecycle_state', 'active')->get(['id']) as $organization) {
            if ($decision->decide($actor, 'workflow.work', StructureScope::organization((string) $organization->id))->allowed) {
                $authorized[] = (string) $organization->id;
            }
        }
        sort($authorized);

        return $authorized;
    }

    /**
     * @param  list<string>  $candidateBranchIds
     * @return list<string>
     */
    private function authorizedBranches(Actor $actor, array $candidateBranchIds): array
    {
        $decision = app(AccessDecision::class);
        $authorized = [];
        foreach (Branch::query()->whereIn('id', $candidateBranchIds)->get() as $branch) {
            if ($decision->decide($actor, 'workflow.work', $branch->structureScope())->allowed) {
                $authorized[] = (string) $branch->id;
            }
        }
        sort($authorized);

        return $authorized;
    }
}
