<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\WorkManagement\Commands\MaintainQueueMembership;
use App\Modules\WorkManagement\Commands\MaintainWorkItem;
use App\Modules\WorkManagement\Models\QueueMembership;
use App\Modules\WorkManagement\Models\WorkItem;
use App\Modules\WorkManagement\Queries\WorkItemQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Coordination transport; linked domain actions remain separate commands. */
final class WorkManagementApiController extends Controller
{
    public function __construct(
        private readonly WorkItemQuery $items,
        private readonly MaintainWorkItem $maintain,
        private readonly MaintainQueueMembership $memberships,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => ['items' => $this->items->forActor($this->actor())]]);
    }

    public function transition(Request $request, string $workItemId): JsonResponse
    {
        $input = $request->validate([
            'to_state' => ['required', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $workItem = WorkItem::query()->whereKey($workItemId)->firstOrFail();
        $result = $this->maintain->transition(
            $this->actor(),
            $workItem,
            trim((string) $input['to_state']),
            $this->idempotencyKey('workflow.work.transition'),
            ($input['note'] ?? null) === null ? null : trim((string) $input['note']),
        );

        return response()->json(['data' => $result]);
    }

    public function grantQueueMembership(Request $request): JsonResponse
    {
        $input = $request->validate([
            'actor_id' => ['required', 'string', 'max:36'],
            'queue_key' => ['required', 'string', 'max:100'],
            'branch_id' => ['nullable', 'string', 'max:36'],
            'organization_id' => ['nullable', 'string', 'max:36'],
        ]);
        $result = $this->memberships->grant(
            $this->actor(),
            trim((string) $input['actor_id']),
            trim((string) $input['queue_key']),
            ($input['branch_id'] ?? null) === null ? null : trim((string) $input['branch_id']),
            $this->idempotencyKey('workflow.queue.grant'),
            ($input['organization_id'] ?? null) === null ? null : trim((string) $input['organization_id']),
        );

        return response()->json(['data' => $result], 201);
    }

    public function revokeQueueMembership(string $membershipId): JsonResponse
    {
        $membership = QueueMembership::query()->whereKey($membershipId)->firstOrFail();

        return response()->json(['data' => $this->memberships->revoke($this->actor(), $membership, $this->idempotencyKey('workflow.queue.revoke'))]);
    }
}
