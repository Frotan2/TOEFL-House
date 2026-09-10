<?php

declare(strict_types=1);

namespace App\Modules\Communication\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Communication\Models\Thread;
use App\Modules\Communication\Models\ThreadParticipant;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\BranchScopedAccess;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Idempotency\IdempotentExecution;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;

/** Creates communication coordination only; source-domain actions remain authoritative elsewhere. */
final class MaintainThread
{
    public const CAPABILITY = 'communication.thread.manage';

    private readonly BranchScopedAccess $scoped;

    public function __construct(
        private readonly AccessDecision $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,
    ) {
        $this->scoped = new BranchScopedAccess($access);
    }

    /** @return array{thread_id: string, correlation_id: string} */
    public function create(
        Actor $actor,
        string $organizationId,
        ?string $branchId,
        ?string $subject,
        string $idempotencyKey,
    ): array {
        $organizationId = trim($organizationId);
        $branchId = $branchId === null ? null : trim($branchId);
        $branchId = $branchId === '' ? null : $branchId;
        $payload = hash('sha256', implode('|', ['communication.thread.create', $organizationId, $branchId ?? '', trim((string) $subject), $actor->actorId]));

        try {
            return $this->idempotency->execute('communication.thread.create', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($actor, $organizationId, $branchId, $subject): array {
                    if (! Organization::query()->whereKey($organizationId)->where('lifecycle_state', 'active')->exists()) {
                        throw BusinessRejection::forCode('communication.thread_organization_unknown', 'a thread requires an active organization');
                    }
                    if ($branchId !== null) {
                        $branch = Branch::query()->whereKey($branchId)->where('lifecycle_state', 'active')->first();
                        if ($branch === null) {
                            throw BusinessRejection::forCode('communication.thread_branch_unknown', 'a branch thread requires an active branch');
                        }
                        $scope = $branch->structureScope();
                        if (trim($scope->organizationId) !== $organizationId) {
                            throw BusinessRejection::forCode('communication.thread_scope_invalid', 'thread branch and organization provenance must agree');
                        }
                        $this->scoped->require($actor, self::CAPABILITY, $branchId, 'communication.thread_denied', $organizationId);
                        $scopeType = 'branch';
                    } else {
                        $outcome = $this->access->decide($actor, self::CAPABILITY, StructureScope::organization($organizationId));
                        if (! $outcome->allowed) {
                            throw AuthorizationDenied::forCode('communication.thread_denied', $outcome->reason);
                        }
                        $scopeType = 'organization';
                    }

                    $thread = Thread::query()->create([
                        'id' => RandomIdentifier::new(),
                        'subject' => $subject !== null ? trim($subject) : null,
                        'scope_type' => $scopeType,
                        'organization_id' => $organizationId,
                        'branch_id' => $branchId,
                        'lifecycle_state' => 'open',
                        'created_by' => $actor->actorId,
                        'last_activity_at' => now(),
                    ]);
                    ThreadParticipant::query()->create([
                        'id' => RandomIdentifier::new(),
                        'thread_id' => $thread->id,
                        'actor_id' => $actor->actorId,
                        'participant_role' => 'sender',
                        'joined_at' => now(),
                    ]);
                    $event = $this->audit->record($actor->actorId, 'communication.thread.create', 'communication_thread', $thread->id, null, [
                        'organization_id' => $organizationId,
                        'branch_id' => $branchId,
                    ]);

                    return ['thread_id' => $thread->id, 'correlation_id' => $event->correlation_id];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $actor, 'communication.thread.create', 'communication_thread', $organizationId);
        }
    }
}
