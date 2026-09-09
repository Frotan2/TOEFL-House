<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Queries\ScopedAuditQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Canonical, server-scoped audit evidence read surface. */
final class AuditApiController extends Controller
{
    public function workspace(Request $request): JsonResponse
    {
        $this->requireOrganizationRead('governance.config', 'audit.api.workspace');
        $organizations = $this->authorizedOrganizations('governance.config');
        $branches = $this->authorizedBranches('governance.config');
        $query = ScopedAuditQuery::forScope($organizations, $branches);

        $operation = trim((string) $request->query('operation', ''));
        $actorId = trim((string) $request->query('actor_id', ''));
        $targetType = trim((string) $request->query('target_type', ''));
        if ($operation !== '') {
            $query->where('operation', $operation);
        }
        if ($actorId !== '') {
            $query->where('actor_id', $actorId);
        }
        if ($targetType !== '') {
            $query->where('target_type', $targetType);
        }

        $events = (clone $query)
            ->orderByDesc('occurred_at')
            ->limit(300)
            ->get([
                'id', 'actor_id', 'operation', 'target_type', 'target_id', 'correlation_id',
                'before_state', 'after_state', 'occurred_at',
            ]);

        $operations = ScopedAuditQuery::forScope($organizations, $branches)
            ->select('operation')
            ->distinct()
            ->orderBy('operation')
            ->limit(200)
            ->pluck('operation')
            ->values()
            ->all();

        return response()->json(['data' => [
            'scope' => ['organization_ids' => array_values($organizations), 'branch_ids' => array_values($branches)],
            'events' => $events->map(static fn (AuditEvent $event): array => [
                'id' => (string) $event->id,
                'actor_id' => (string) $event->actor_id,
                'operation' => (string) $event->operation,
                'target_type' => (string) $event->target_type,
                'target_id' => (string) $event->target_id,
                'correlation_id' => (string) $event->correlation_id,
                'before_state' => $event->before_state,
                'after_state' => $event->after_state,
                'occurred_at' => $event->occurred_at?->toISOString(),
                'evidence_policy' => 'immutable_append_only',
            ])->values()->all(),
            'operations' => $operations,
            'filters' => ['operation' => $operation, 'actor_id' => $actorId, 'target_type' => $targetType],
        ]]);
    }
}
