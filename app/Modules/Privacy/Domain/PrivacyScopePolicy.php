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

/**
 * Canonical privacy target-scope invariant.
 *
 * A caller may name a disclosure/export scope, but that declaration never
 * creates authority. The server resolves both the subject provenance and the
 * declared structural unit and requires the declared scope to be inside the
 * subject's current organizational boundary. Capability decisions remain
 * owned by AccessDecision; this class only proves target provenance.
 */
final class PrivacyScopePolicy
{
    public static function assertDeclaredScopeMatchesSubject(
        string $subjectPersonId,
        string $scopeType,
        string $scopeId,
    ): StructureScope {
        $subjectScope = PersonBranchScope::resolve($subjectPersonId);
        $declared = self::resolve($scopeType, $scopeId);

        if ($scopeType === 'subject') {
            if (trim($scopeId) !== trim($subjectPersonId)) {
                throw BusinessRejection::forCode('privacy.scope_mismatch', 'subject scope must identify the disclosed subject');
            }

            return $subjectScope;
        }

        if ($declared->organizationId !== $subjectScope->organizationId) {
            throw BusinessRejection::forCode('privacy.scope_mismatch', 'privacy scope must remain inside the subject organization');
        }

        if ($declared->branchId !== null && $declared->branchId !== $subjectScope->branchId) {
            throw BusinessRejection::forCode('privacy.scope_mismatch', 'privacy scope must remain inside the subject branch');
        }

        return $declared;
    }

    private static function resolve(string $scopeType, string $scopeId): StructureScope
    {
        $scopeId = trim($scopeId);
        if ($scopeId === '') {
            throw BusinessRejection::forCode('privacy.scope_required', 'privacy scope id is required');
        }

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
        if ($organization->lifecycle_state !== 'active') {
            throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        }

        return StructureScope::organization($organization->id);
    }

    private static function campus(string $id): StructureScope
    {
        $campus = Campus::query()->whereKey($id)->firstOrFail();
        if ($campus->lifecycle_state !== 'active') {
            throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        }

        return $campus->structureScope();
    }

    private static function branch(string $id): StructureScope
    {
        $branch = Branch::query()->whereKey($id)->firstOrFail();
        if ($branch->lifecycle_state !== 'active') {
            throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        }

        return $branch->structureScope();
    }

    private static function department(string $id): StructureScope
    {
        $department = Department::query()->whereKey($id)->firstOrFail();
        if ($department->lifecycle_state !== 'active') {
            throw BusinessRejection::forCode('privacy.scope_inactive', 'privacy scope must be active');
        }

        return $department->structureScope();
    }
}
