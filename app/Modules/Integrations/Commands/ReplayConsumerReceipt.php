<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Outbox\Domain\EventConsumerCatalog;
use App\Modules\Outbox\Models\ConsumerReceipt;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Idempotency\IdempotentExecution;
use Illuminate\Support\Facades\DB;

/**
 * Manual recovery boundary for consumer dead letters. Replay preserves the
 * immutable event and consumer identity, resets only delivery-attempt state,
 * and records an explicit operator intervention. The normal-domain consumer
 * remains the sole authority for any projection side effect.
 */
final class ReplayConsumerReceipt
{
    public const CAPABILITY = 'integrations.review';

    public function __construct(
        private readonly AccessDecision $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,
    ) {}

    /** @return array{receipt_id: string, status: string, replay_count: int, correlation_id: string} */
    public function replay(Actor $actor, ConsumerReceipt $receipt, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', [
            'integrations.consumer.replay',
            $receipt->id,
            $receipt->consumer_key,
            $actor->actorId,
        ]));

        try {
            return $this->idempotency->execute('integrations.consumer.replay', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($actor, $receipt): array {
                    $this->require($actor);

                    /** @var ConsumerReceipt $locked */
                    $locked = ConsumerReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'dead_letter') {
                        throw BusinessRejection::forCode('integrations.consumer_replay_not_dead', 'only dead-lettered consumer receipts are replayable');
                    }
                    if ($locked->event_id === '' || $locked->consumer_key === '') {
                        throw BusinessRejection::forCode('integrations.consumer_replay_invalid_identity', 'a consumer receipt must retain its event and consumer identity');
                    }

                    // Fail closed if the consumer was retired from the allowlist.
                    EventConsumerCatalog::consumerFor($locked->consumer_key);

                    $locked->forceFill([
                        'status' => 'pending',
                        'attempts' => 0,
                        'next_attempt_at' => null,
                        'last_error' => null,
                        'claimed_at' => null,
                        'lease_until' => null,
                        'processed_at' => null,
                        'replay_count' => ((int) ($locked->replay_count ?? 0)) + 1,
                    ]);
                    $locked->save();
                    $event = $this->audit->record($actor->actorId, 'integrations.consumer.replay', 'event_consumer_receipt', $locked->id, null, [
                        'event_id' => $locked->event_id,
                        'consumer_key' => $locked->consumer_key,
                        'replay_count' => $locked->replay_count,
                    ]);

                    return [
                        'receipt_id' => (string) $locked->id,
                        'status' => (string) $locked->status,
                        'replay_count' => (int) $locked->replay_count,
                        'correlation_id' => $event->correlation_id,
                    ];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $actor, 'integrations.consumer.replay', 'event_consumer_receipt', $receipt->id);
        }
    }

    private function require(Actor $actor): void
    {
        $outcome = $this->access->decide($actor, self::CAPABILITY, null);
        if (! $outcome->allowed) {
            throw AuthorizationDenied::forCode('integrations.consumer_replay_denied', $outcome->reason);
        }
    }
}
