<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use App\Support\Errors\BusinessRejection;

/**
 * Canonical duplicate-topology guard. The database unique indexes remain the
 * hard, concurrency-safe authority; this guard gives every command path the
 * same whitespace-insensitive, case-insensitive name rule with a stable
 * business error, so duplicate units can never slip in via a casing variant
 * and a collision is a 409 business outcome rather than a raw database error.
 */
final class UniqueTopologyName
{
    public function requireAvailable(
        string $unitType,
        string $name,
        ?string $scopeType = null,
        ?string $scopeId = null,
        ?string $exceptUnitId = null,
    ): void {
        $normalized = StructureChangeDefinition::normalizeName($name);
        $match = static fn ($query) => $query->whereRaw(
            'lower(btrim(regexp_replace(name, \'\\s+\', \' \', \'g\'))) = ?',
            [$normalized],
        );

        $exists = match ($unitType) {
            'organization' => $match(Organization::query()->when($exceptUnitId !== null, fn ($query) => $query->where('id', '!=', $exceptUnitId)))->exists(),
            'campus' => $match(Campus::query()
                ->where('organization_id', $scopeId)
                ->when($exceptUnitId !== null, fn ($query) => $query->where('id', '!=', $exceptUnitId)))->exists(),
            // Branch names are globally unique: cross-organization branch
            // name collisions would make provenance ambiguous.
            'branch' => $match(Branch::query()
                ->when($exceptUnitId !== null, fn ($query) => $query->where('id', '!=', $exceptUnitId)))->exists(),
            'department' => $match(Department::query()
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->when($exceptUnitId !== null, fn ($query) => $query->where('id', '!=', $exceptUnitId)))->exists(),
            default => false,
        };

        if ($exists) {
            throw BusinessRejection::forCode(
                'organization.structure.duplicate',
                sprintf('a %s with this name already exists in the chosen scope', $unitType),
            );
        }
    }
}
