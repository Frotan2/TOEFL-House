<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Queries;

use Illuminate\Support\Facades\DB;

/**
 * Read-only operational snapshot of integration/outbox recovery state.
 * It reports pressure and stale leases without mutating domain truth.
 */
final class IntegrationHealth
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $now = now();
        $delivery = $this->deliveryCounts($now);
        $consumer = $this->consumerCounts($now);
        $jobs = $this->jobCounts($now);
        $inbound = $this->inboundCounts();

        $critical = ($delivery['dead_letter'] + $consumer['dead_letter'] + $delivery['expired_processing'] + $consumer['expired_processing']) > 0;
        $warning = ($delivery['due'] + $consumer['due'] + $delivery['failed'] + $consumer['failed'] + $inbound['received_unprocessed'] + $jobs['failed']) > 0;

        return [
            'status' => $critical ? 'critical' : ($warning ? 'degraded' : 'healthy'),
            'as_of' => $now->toIso8601String(),
            'deliveries' => $delivery,
            'consumer_receipts' => $consumer,
            'job_runs' => $jobs,
            'inbound_events' => $inbound,
        ];
    }

    /** @return array<string, int> */
    private function deliveryCounts($now): array
    {
        return [
            'queued' => $this->count('integration_deliveries', 'status', 'queued'),
            'failed' => $this->count('integration_deliveries', 'status', 'failed'),
            'processing' => $this->count('integration_deliveries', 'status', 'processing'),
            'dead_letter' => $this->count('integration_deliveries', 'status', 'dead_letter'),
            'due' => (int) DB::table('integration_deliveries')->whereIn('status', ['queued', 'failed'])->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now))->count(),
            'expired_processing' => (int) DB::table('integration_deliveries')->where('status', 'processing')->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', $now))->count(),
        ];
    }

    /** @return array<string, int> */
    private function consumerCounts($now): array
    {
        return [
            'pending' => $this->count('event_consumer_receipts', 'status', 'pending'),
            'failed' => $this->count('event_consumer_receipts', 'status', 'failed'),
            'processing' => $this->count('event_consumer_receipts', 'status', 'processing'),
            'succeeded' => $this->count('event_consumer_receipts', 'status', 'succeeded'),
            'dead_letter' => $this->count('event_consumer_receipts', 'status', 'dead_letter'),
            'due' => (int) DB::table('event_consumer_receipts')->whereIn('status', ['pending', 'failed'])->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))->count(),
            'expired_processing' => (int) DB::table('event_consumer_receipts')->where('status', 'processing')->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', $now))->count(),
        ];
    }

    /** @return array<string, int> */
    private function jobCounts($now): array
    {
        return [
            'queued' => $this->count('job_runs', 'status', 'queued'),
            'running' => $this->count('job_runs', 'status', 'running'),
            'failed' => $this->count('job_runs', 'status', 'failed'),
            'succeeded' => $this->count('job_runs', 'status', 'succeeded'),
            'due' => (int) DB::table('job_runs')->whereIn('status', ['queued', 'failed'])->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now))->count(),
        ];
    }

    /** @return array<string, int> */
    private function inboundCounts(): array
    {
        return [
            'received_unprocessed' => $this->count('inbound_events', 'status', 'received'),
            'processed' => $this->count('inbound_events', 'status', 'processed'),
            'rejected' => $this->count('inbound_events', 'status', 'rejected'),
        ];
    }

    private function count(string $table, string $column, string $value): int
    {
        return (int) DB::table($table)->where($column, $value)->count();
    }
}
