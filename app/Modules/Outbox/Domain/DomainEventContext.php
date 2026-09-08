<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain;

use App\Modules\Audit\Models\AuditEvent;
use Illuminate\Support\Facades\DB;

/**
 * Stable event-envelope context. Missing operational provenance is explicit
 * `unknown`; it is never interpreted as organization-wide visibility.
 *
 * Producers must declare `branch_id` only for a single-branch fact. A
 * multi-branch fact may declare `organization_id` when every participating
 * branch belongs to that one active organization; otherwise it remains
 * unknown. The envelope never chooses the first branch from a relationship.
 * An explicit null/empty base declaration also blocks an intent from
 * widening or repairing provenance after the audited change was recorded.
 */
final class DomainEventContext
{
    /**
     * Resolves the organization that currently owns a branch, via its
     * effective campus assignment. Returns null when the branch has no
     * resolvable active provenance, which keeps the envelope explicit rather
     * than inventing organization-wide visibility.
     */
    private static function organizationForBranch(string $branchId): ?string
    {
        $organizationId = DB::table('campus_assignments as ca')
            ->join('campuses as c', 'c.id', '=', 'ca.campus_id')
            ->join('organizations as o', 'o.id', '=', 'c.organization_id')
            ->whereRaw('btrim(ca.branch_id) = ?', [trim($branchId)])
            ->whereDate('ca.effective_from', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ca.effective_to')->orWhereDate('ca.effective_to', '>', now());
            })
            ->where('c.lifecycle_state', 'active')
            ->where('o.lifecycle_state', 'active')
            ->value('c.organization_id');

        $organizationId = is_string($organizationId) ? trim($organizationId) : null;

        return ($organizationId === null || $organizationId === '') ? null : $organizationId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    public static function from(AuditEvent $auditEvent, array $payload): array
    {
        $baseFields = [];
        $intentFields = [];
        foreach (['before', 'after'] as $part) {
            if (! is_array($payload[$part] ?? null)) {
                continue;
            }
            // The audited change is authoritative. `after` intentionally
            // overlays `before` for lifecycle/provenance transitions.
            $baseFields = array_merge($baseFields, $payload[$part]);
            foreach (['workflow', 'notification'] as $intentKey) {
                if (is_array($payload[$part][$intentKey] ?? null)) {
                    $intentFields = array_merge($intentFields, $payload[$part][$intentKey]);
                }
            }
        }
        foreach (['workflow', 'notification'] as $intentKey) {
            if (is_array($payload[$intentKey] ?? null)) {
                $intentFields = array_merge($intentFields, $payload[$intentKey]);
            }
        }

        $provenanceKeys = ['branch_id', 'originating_branch_id', 'current_home_branch_id', 'origin_branch_id'];
        $organizationDeclared = array_key_exists('organization_id', $baseFields);
        $branchDeclared = self::hasAny($baseFields, $provenanceKeys);
        $branchId = self::first($baseFields, $provenanceKeys);
        $organizationId = self::first($baseFields, ['organization_id']);
        // Once any audited provenance field is explicitly declared, intent
        // metadata cannot fill in a missing sibling field. This prevents an
        // intent from converting a partially scoped audited change into a
        // broader or differently scoped event envelope.
        if (! $branchDeclared && ! $organizationDeclared) {
            $branchId = self::first($intentFields, $provenanceKeys);
            $organizationId = self::first($intentFields, ['organization_id']);
        }

        // A branch-scoped envelope must also carry the organization that owns
        // the branch: `domain_events_context_provenance_guard` requires both
        // ids and that they identify the same active structure. The
        // organization is derived from the branch on the server rather than
        // trusted from the payload, so a producer cannot widen its own scope.
        if ($branchId !== null && $organizationId === null) {
            $organizationId = self::organizationForBranch($branchId);
        }

        // Active-topology constraint: an envelope may name a branch only when
        // that branch is operationally active (the database guard is the hard
        // backstop). An audited change naming a not-yet-active branch — e.g.
        // the initial campus attribution recorded while the branch is still
        // draft — keeps its full evidence in the payload but degrades to the
        // declared active organization scope (or unknown when none exists);
        // it never fabricates a branch scope the guard would reject.
        if ($branchId !== null) {
            $branchActive = DB::table('branches')
                ->where('id', $branchId)
                ->where('lifecycle_state', 'active')
                ->exists();
            if (! $branchActive) {
                $branchId = null;
            }
        }

        $hasProvenance = $branchId !== null || $organizationId !== null;

        return [
            'scope_type' => $branchId !== null ? 'branch' : ($organizationId !== null ? 'organization' : 'unknown'),
            'scope_provenance' => $hasProvenance ? 'declared_on_audited_change' : 'not_available',
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'actor_id' => $auditEvent->actor_id,
            'target_type' => $auditEvent->target_type,
            'target_id' => $auditEvent->target_id,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $keys
     */
    private static function first(array $fields, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string> $keys
     */
    private static function hasAny(array $fields, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $fields)) {
                return true;
            }
        }

        return false;
    }
}
