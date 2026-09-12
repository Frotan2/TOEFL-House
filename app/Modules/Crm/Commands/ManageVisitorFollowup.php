<?php

declare(strict_types=1);

namespace App\Modules\Crm\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Crm\Domain\CrmAccess;
use App\Modules\Crm\Models\Visitor;
use App\Modules\Crm\Models\VisitorFollowup;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Idempotency\IdempotentExecution;
use Illuminate\Support\Facades\DB;

/**
 * Advance a scheduled follow-up to done/cancelled. Visitor provenance is the
 * locking authority shared with conversion/interaction commands, so visitor
 * state is locked before the follow-up row to keep competing CRM workflows on
 * one lock order and avoid conversion/follow-up deadlocks.
 */
final class ManageVisitorFollowup
{
    public const CAPABILITY = 'crm.followup';

    public function __construct(
        private readonly CalendarAuthority $calendar,

        private readonly CrmAccess $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,

    ) {}

    /** @return array{followup_id: string, status: string, correlation_id: string} */
    public function complete(Actor $actor, VisitorFollowup $followup, string $idempotencyKey): array
    {
        return $this->transition($actor, $followup, VisitorFollowup::STATUS_DONE, $idempotencyKey);
    }

    /** @return array{followup_id: string, status: string, correlation_id: string} */
    public function cancel(Actor $actor, VisitorFollowup $followup, string $idempotencyKey): array
    {
        return $this->transition($actor, $followup, VisitorFollowup::STATUS_CANCELLED, $idempotencyKey);
    }

    /** @return array{followup_id: string, status: string, correlation_id: string} */
    private function transition(Actor $actor, VisitorFollowup $followup, string $toStatus, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', ['crm.followup.transition', $followup->id, $toStatus, $actor->actorId]));

        try {
            return $this->idempotency->execute('crm.followup.transition', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($actor, $followup, $toStatus): array {
                    /** @var VisitorFollowup $snapshot */
                    $snapshot = VisitorFollowup::query()->whereKey($followup->id)->firstOrFail();

                    /** @var Visitor $visitor */
                    $visitor = Visitor::query()->whereKey($snapshot->visitor_id)->lockForUpdate()->firstOrFail();

                    // Match conversion and interaction lock order: visitor first,
                    // then dependent CRM records. This prevents a conversion
                    // transaction waiting on a follow-up while a follow-up
                    // transaction waits on the same visitor.
                    /** @var VisitorFollowup $locked */
                    $locked = VisitorFollowup::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
                    $this->access->require($actor, self::CAPABILITY, $visitor->origin_branch_id, 'crm.followup_denied');
                    if (! $visitor->isOpen()) {
                        throw BusinessRejection::forCode('crm.followup_closed_visitor', 'a follow-up cannot be changed after its visitor reaches a terminal state');
                    }
                    if ($locked->visitor_id !== $visitor->id) {
                        throw BusinessRejection::forCode('crm.followup_visitor_mismatch', 'the follow-up target changed and no longer matches its visitor');
                    }
                    if ($locked->status !== VisitorFollowup::STATUS_OPEN) {
                        throw BusinessRejection::forCode('crm.followup_invalid_transition', sprintf('a %s follow-up cannot transition to %s', $locked->status, $toStatus));
                    }

                    $before = ['status' => $locked->status];
                    $locked->forceFill([
                        'status' => $toStatus,
                        'completed_by' => $actor->actorId,
                        'completed_at' => now()->toDateTimeString(),
                    ]);
                    $locked->save();
                    $after = ['status' => $toStatus, 'visitor_id' => $visitor->id, 'origin_branch_id' => $visitor->origin_branch_id];
                    if ($toStatus === VisitorFollowup::STATUS_CANCELLED
                        && $visitor->origin_branch_id !== null
                        && UserAccount::query()->where('person_id', $locked->assigned_to)->where('account_state', UserAccount::STATE_ACTIVE)->exists()) {
                        $scope = $this->branchScope($visitor->origin_branch_id);
                        if ($scope !== []) {
                            $after += $scope + [
                                'notification' => [
                                    'recipient_actor_id' => $locked->assigned_to,
                                    'source_type' => 'visitor_followup',
                                    'source_id' => $locked->id,
                                    'title' => 'CRM follow-up cancelled: '.$locked->title,
                                    'severity' => 'info',
                                    'dedupe_key' => 'crm.followup.cancelled.'.$locked->id,
                                ],
                            ];
                        }
                    }
                    $event = $this->audit->record($actor->actorId, 'crm.followup.transition', 'visitor_followup', $locked->id, $before, $after);

                    return ['followup_id' => $locked->id, 'status' => $toStatus, 'correlation_id' => $event->correlation_id];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $actor, 'crm.followup.transition', 'visitor_followup', $followup->id);
        }
    }

    /** @return array{branch_id: string, organization_id: string}|array{} */
    private function branchScope(?string $branchId): array
    {
        $branchId = trim((string) ($branchId ?? ''));
        if ($branchId === '') {
            return [];
        }
        $scope = DB::table('branches as b')
            ->join('campus_assignments as ca', 'ca.branch_id', '=', 'b.id')
            ->join('campuses as c', 'c.id', '=', 'ca.campus_id')
            ->join('organizations as o', 'o.id', '=', 'c.organization_id')
            ->where('b.id', $branchId)
            ->where('b.lifecycle_state', 'active')
            ->where('c.lifecycle_state', 'active')
            ->where('o.lifecycle_state', 'active')
            ->where('ca.effective_from', '<=', $this->calendar->todayAsString())
            ->where(fn ($query) => $query->whereNull('ca.effective_to')->orWhere('ca.effective_to', '>', $this->calendar->todayAsString()))
            ->first(['b.id as branch_id', 'c.organization_id']);

        return $scope === null ? [] : [
            'branch_id' => (string) $scope->branch_id,
            'organization_id' => (string) $scope->organization_id,
        ];
    }
}
