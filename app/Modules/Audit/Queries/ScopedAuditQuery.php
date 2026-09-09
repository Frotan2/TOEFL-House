<?php

declare(strict_types=1);

namespace App\Modules\Audit\Queries;

use App\Modules\Audit\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;

/** Canonical read-side scope projection for audit evidence. */
final class ScopedAuditQuery
{
    /**
     * @param list<string> $organizationIds
     * @param list<string> $branchIds
     * @return Builder<AuditEvent>
     */
    public static function forScope(array $organizationIds, array $branchIds): Builder
    {
        $query = AuditEvent::query();
        $query->where(function (Builder $scope) use ($organizationIds, $branchIds): void {
            $hasMatch = false;
            foreach ($organizationIds as $organizationId) {
                $method = $hasMatch ? 'orWhere' : 'where';
                $scope->{$method}(function (Builder $organization) use ($organizationId): void {
                    $organization->whereRaw("after_state->>'organization_id' = ?", [$organizationId])
                        ->orWhereRaw("before_state->>'organization_id' = ?", [$organizationId])
                        ->orWhere(function (Builder $target) use ($organizationId): void {
                            $target->where('target_type', 'organization')->where('target_id', $organizationId);
                        });
                });
                $hasMatch = true;
            }
            foreach ($branchIds as $branchId) {
                $method = $hasMatch ? 'orWhere' : 'where';
                $scope->{$method}(function (Builder $branch) use ($branchId): void {
                    $branch->whereRaw("after_state->>'branch_id' = ?", [$branchId])
                        ->orWhereRaw("before_state->>'branch_id' = ?", [$branchId])
                        ->orWhere(function (Builder $target) use ($branchId): void {
                            $target->where('target_type', 'branch')->where('target_id', $branchId);
                        });
                });
                $hasMatch = true;
            }
            if (! $hasMatch) {
                $scope->whereRaw('1 = 0');
            }
        });

        return $query;
    }
}
