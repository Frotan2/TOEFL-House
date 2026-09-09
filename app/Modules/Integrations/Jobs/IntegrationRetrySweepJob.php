<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Jobs;

use App\Modules\Integrations\Domain\DeliveryProcessor;
use App\Modules\Integrations\Domain\JobHandler;
use App\Modules\Integrations\Models\IntegrationDelivery;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;

/**
 * Scheduled integration retry sweep (architecture 16): consumes committed
 * delivery facts, retries due work through the shared delivery core, and
 * bounds each scheduler invocation so a growing queue cannot create an
 * unbounded-running job. Each delivery is processed independently.
 */
final class IntegrationRetrySweepJob implements JobHandler
{
    private const DEFAULT_BATCH = 100;
    private const MAX_BATCH = 500;

    public function __construct(
        private readonly DeliveryProcessor $processor,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, int>
     */
    public function handle(array $context): array
    {
        $runBy = trim((string) ($context['run_by'] ?? ''));
        if ($runBy === '') {
            throw BusinessRejection::forCode('integrations.retry_operator_required', 'a scheduled retry sweep requires a durable authenticated run_by actor');
        }

        $batch = (int) ($context['batch'] ?? self::DEFAULT_BATCH);
        $batch = max(1, min(self::MAX_BATCH, $batch));

        $operator = new Actor($runBy, 'Integration Sweep');
        $due = IntegrationDelivery::query()
            ->where(function ($state): void {
                $state->whereIn('status', ['queued', 'failed'])
                    ->orWhere(function ($lease): void {
                        $lease->where('status', 'processing')
                            ->where(fn ($expired) => $expired->whereNull('lease_until')->orWhere('lease_until', '<=', now()));
                    });
            })
            ->where(fn ($query) => $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->orderBy('created_at')
            ->limit($batch)
            ->pluck('id');

        $summary = ['considered' => $due->count(), 'delivered' => 0, 'retry_scheduled' => 0, 'dead_letter' => 0, 'skipped' => 0];
        foreach ($due as $deliveryId) {
            $outcome = $this->processor->processId($deliveryId, $operator);
            $summary['skipped'] += str_starts_with($outcome['outcome'], 'skipped') ? 1 : 0;
            $summary['delivered'] += $outcome['outcome'] === 'delivered' ? 1 : 0;
            $summary['retry_scheduled'] += $outcome['outcome'] === 'retry_scheduled' ? 1 : 0;
            $summary['dead_letter'] += $outcome['outcome'] === 'dead_letter' ? 1 : 0;
        }

        return $summary;
    }
}
