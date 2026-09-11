<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Modules\Access\Models\AccessPolicy;
use App\Modules\Access\Models\Position;
use App\Support\Errors\AuthorizationDenied;
use Carbon\CarbonImmutable;
use App\Modules\Calendar\CalendarAuthority;

/**
 * Canonical guard for conferring a position's effective authority. Possessing
 * the generic assignment capability is necessary but not sufficient: an
 * actor may not use position assignment to introduce capabilities they could
 * not otherwise exercise. Access-policy administrators remain able to confer
 * any position they are explicitly authorized to administer.
 */
final class PositionConferability
{
    public function __construct(
        private readonly CalendarAuthority $calendar,
private readonly AccessDecision $access
    ) {}

    public function require(Actor $actor, Position $position, StructureScope $scope): void
    {
        $assignOutcome = $this->access->decide($actor, 'access.assign_position', $scope);
        if (! $assignOutcome->allowed) {
            throw AuthorizationDenied::forCode('access.assign_position_denied', $assignOutcome->reason);
        }

        // The policy-management capability is the explicit administrative
        // override: without it, assignment cannot be used as a privilege
        // escalation vector. The check is made in the target scope, not as a
        // branchless wildcard, so a scoped access administrator stays scoped.
        $policyAdminOutcome = $this->access->decide($actor, 'access.define_policy', $scope);
        if ($policyAdminOutcome->allowed) {
            return;
        }

        $today = $this->calendar->todayAsString();
        $roleIds = AccessPolicy::query()
            ->where('binding_type', 'position')
            ->where('binding_id', $position->id)
            ->where('grants_type', AccessPolicy::GRANTS_ROLE)
            ->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $today))
            ->pluck('grants_id')
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        foreach ($roleIds as $roleId) {
            $permissions = AccessPolicy::query()
                ->where('binding_type', 'role')
                ->where('binding_id', $roleId)
                ->where('grants_type', AccessPolicy::GRANTS_PERMISSION)
                ->where('effective_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $today))
                ->pluck('permission')
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->unique()
                ->values()
                ->all();

            foreach ($permissions as $permission) {
                if (! $this->access->decide($actor, $permission, $scope)->allowed) {
                    throw AuthorizationDenied::forCode(
                        'access.position_conferability_denied',
                        sprintf('the target position confers capability %s outside the actor\'s authority', $permission),
                    );
                }
            }
        }
    }
}
