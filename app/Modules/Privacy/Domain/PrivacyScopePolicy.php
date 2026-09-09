<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Domain;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Support\Authorization\PersonBranchScope;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Canonical server-owned privacy target-scope invariant. */
final class PrivacyScopePolicy
{
    public static function assertDeclaredScopeMatchesSubject(string $subjectPersonId, string $scopeType, string $scopeId): StructureScope
    {
        $subjectScope = PersonBranchScope::resolve($subjectPersonId);
        $scopeId = trim($scopeId);

        if ($scopeType === 'subject') {
            if ($scopeId !== trim($subjectPersonId)) {
                throw BusinessRejection::forCode('privacy.scope_mismatch', 'subject scope must identify the disclosed subject');
            }
            return $subjectScope;
        }

        $declared = self::resolve($scopeType, $scopeId);
        if ($declared->organizationId !== $subjectScope->organizationId) {
            throw BusinessRejection::forCode('privacy.scope_mismatch', 'privacy scope must remain inside the subject organization');
        }
        if ($declared->campusId !== null && $declared->campusId !== $subjectScope->campusId) {
            throw BusinessRejection::forCode('privacy.scope_mismatch', 'privacy scope must remain inside the subject campus');
        }
        if ($declared->branchId !== null && $declared->branchId !== $subjectScope->branchId) {
            throw BusinessRejection::forCode('privacy.scope_mismatch', 'privacy scope must remain inside the subject branch');
        }
        if ($declared->departmentId !== null && $declared->departmentId !== $subjectScope->departmentId) {
            throw BusinessRejection::forCode('privacy.scope_mismatch', 'privacy scope must remain inside the subject department');
        }

        return $declared;
    }

    private static function resolve(string $scopeType, string $scopeId): StructureScope
    {
        if ($scopeId === '') throw BusinessRejection::forCode('privacy.scope_required', 'privacy scope id is required');
        try {
            return match ($scopeType) {
                'organization' => self::organization($scopeId),
                'campus' => self::campus($scopeId),
                'branch' => self::branch($scopeId),
                'department' => self::department($scopeId),
                default => throw BusinessRejection::forCode('privacy.scope_type_invalid', 'unsupported privacy scope type'),
            };
        } catch (ModelNotFoundException) {
            throw BusinessRejection::forCode('privacy.scope_unknown', 'privacy scope provenance could not be resolved');
        }
    }

    private static function organization(string $id): StructureScope
    {
        $organization = \App\Modules\Organization\Models\Organization::query()->whereKey($id)->firstOrFail();
        if ($organization->lifecycle_state !== 'active') throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        return StructureScope::organization($organization->id);
    }

    private static function campus(string $id): StructureScope
    {
        $campus = Campus::query()->whereKey($id)->firstOrFail();
        if ($campus->lifecycle_state !== 'active') throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        return $campus->structureScope();
    }

    private static function branch(string $id): StructureScope
    {
        $branch = Branch::query()->whereKey($id)->firstOrFail();
        if ($branch->lifecycle_state !== 'active') throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        return $branch->structureScope();
    }

    private static function department(string $id): StructureScope
    {
        $department = Department::query()->whereKey($id)->firstOrFail();
        if ($department->lifecycle_state !== 'active') throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        return $department->structureScope();
    }
}
