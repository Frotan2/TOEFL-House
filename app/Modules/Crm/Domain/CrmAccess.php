<?php

declare(strict_types=1);

namespace App\Modules\Crm\Domain;

use App\Modules\Organization\Models\Branch;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;

/**
 * CRM authorization: every operation checks the capability through the single
 * AccessDecision authority, and — when the record carries branch provenance —
 * against that branch's structure scope (a branch grant covers it via the
 * organization root).
 *
 * Catalog/automation operations are intentionally organization-governed and
 * may use a null scope. Record operations (`crm.visitor` and `crm.followup`)
 * are different: a null branch is unknown provenance and must fail closed.
 */
final class CrmAccess
{
    public function __construct(private readonly AccessDecision $access) {}

    public function require(Actor $actor, string $capability, ?string $branchId = null, string $errorCode = 'crm.denied'): void
    {
        if (($capability === 'crm.visitor' || $capability === 'crm.followup')
            && ($branchId === null || $branchId === '')) {
            throw AuthorizationDenied::forCode($errorCode, 'CRM record provenance is unknown; a branch-scoped target is required');
        }

        $scope = $this->scopeFor($branchId);
        $outcome = $this->access->decide($actor, $capability, $scope);
        if (! $outcome->allowed) {
            throw AuthorizationDenied::forCode($errorCode, $outcome->reason);
        }
    }

    public function scopeFor(?string $branchId): ?StructureScope
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }
        /** @var Branch|null $branch */
        $branch = Branch::query()->find($branchId);
        if ($branch === null) {
            throw BusinessRejection::forCode('crm.branch_unknown', 'the referenced branch does not exist');
        }

        return $branch->structureScope();
    }
}