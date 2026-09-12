<?php

declare(strict_types=1);

namespace App\Modules\Communication\Queries;

use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Communication\Models\NotificationRecipientState;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\ActorBranches;
use App\Support\Authorization\StructureScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Recipient-scoped notification projection; per-recipient state is canonical. */
final class NotificationQuery
{
    /** @return array{status: string, unread_count: int, items: list<array<string, mixed>>} */
    public function forActor(Actor $actor, int $limit = 50): array
    {
        $now = app(CalendarAuthority::class)->nowUtc();
        $candidateBranches = app(ActorBranches::class)->visibleBranchIds($actor);
        /** @var list<string> $branches */
        $branches = $this->authorizedBranches($actor, $candidateBranches);
        /** @var array<string, string> $branchOrganizations */
        $branchOrganizations = $this->branchOrganizations($branches);
        $organizationIds = $this->authorizedOrganizations($actor);
        $scope = static function ($query) use ($organizationIds, $branchOrganizations): void {
            $query->where(function ($scope) use ($organizationIds, $branchOrganizations): void {
                $hasCondition = false;
                if ($organizationIds !== []) {
                    $scope->where(function ($organization) use ($organizationIds): void {
                        $organization->where('scope_type', 'organization')->whereIn('organization_id', $organizationIds);
                    });
                    $hasCondition = true;
                }
                foreach ($branchOrganizations as $branchId => $organizationId) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $scope->{$method}(function ($branch) use ($branchId, $organizationId): void {
                        $branch->where('scope_type', 'branch')
                            ->where('branch_id', $branchId)
                            ->where('organization_id', $organizationId);
                    });
                    $hasCondition = true;
                }
                if (! $hasCondition) {
                    $scope->whereRaw('1 = 0');
                }
            });
        };

        $states = NotificationRecipientState::query()
            ->where('recipient_actor_id', $actor->actorId)
            ->whereIn('lifecycle_state', ['unread', 'read'])
            ->whereHas('notification', function ($query) use ($scope, $now): void {
                $scope($query);
                $query->where(fn ($expires) => $expires->whereNull('expires_at')->orWhere('expires_at', '>', $now));
            })
            ->with('notification')
            ->orderByRaw("CASE WHEN lifecycle_state = 'unread' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 100)))
            ->get();

        $items = array_values($states->map(static function (NotificationRecipientState $state): array {
            $notification = $state->notification;

            return [
                'id' => (string) $notification->id,
                'source_type' => (string) $notification->source_type,
                'source_id' => (string) $notification->source_id,
                'title' => (string) $notification->title,
                'body_ref' => $notification->body_ref,
                'severity' => (string) $notification->severity,
                'scope_type' => (string) $notification->scope_type,
                'organization_id' => $notification->organization_id,
                'branch_id' => $notification->branch_id,
                'status' => (string) $state->lifecycle_state,
                'read_at' => $state->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        })->all());

        return [
            'status' => 'ready',
            'unread_count' => NotificationRecipientState::query()
                ->where('recipient_actor_id', $actor->actorId)
                ->where('lifecycle_state', 'unread')
                ->whereHas('notification', function ($query) use ($scope, $now): void {
                    $scope($query);
                    $query->where(fn ($expires) => $expires->whereNull('expires_at')->orWhere('expires_at', '>', $now));
                })
                ->count(),
            'items' => $items,
        ];
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
            if ($decision->decide($actor, 'communication.notification.read', StructureScope::organization((string) $organization->id))->allowed) {
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
            if ($decision->decide($actor, 'communication.notification.read', $branch->structureScope())->allowed) {
                $authorized[] = (string) $branch->id;
            }
        }
        sort($authorized);

        return $authorized;
    }
}
