<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

use App\Modules\Integrations\Jobs\IntegrationRetrySweepJob;
use App\Modules\Outbox\Jobs\DomainEventRelayJob;
use App\Support\Errors\BusinessRejection;

/**
 * Closed registry of schedulable jobs; a schedule can only reference a
 * catalog job.
 */
final class JobCatalog
{
    /** @var array<string, class-string<JobHandler>> */
    public const HANDLERS = [
        'integrations.retry_sweep' => IntegrationRetrySweepJob::class,
        'outbox.relay' => DomainEventRelayJob::class,
    ];

    /**
     * Capabilities that are required in addition to integrations.jobs, which
     * is checked by the enqueue/process commands themselves. Keep this next
     * to the handler registry: a job may never silently gain a privileged
     * execution path merely because it is added to the scheduler catalog.
     *
     * @var array<string, list<string>>
     */
    public const EXECUTION_CAPABILITIES = [
        'integrations.retry_sweep' => [IntegrationRetrySweepJob::CAPABILITY],
        'outbox.relay' => [DomainEventRelayJob::CAPABILITY],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::HANDLERS);
    }

    /** @return class-string<JobHandler> */
    public static function handlerFor(string $jobKey): string
    {
        $handler = self::HANDLERS[$jobKey] ?? null;
        if ($handler === null) {
            throw BusinessRejection::forCode('integrations.job_unknown', sprintf('job %s is not in the job catalog', $jobKey));
        }

        return $handler;
    }

    /** @return list<string> */
    public static function executionCapabilitiesFor(string $jobKey): array
    {
        // Assert catalog membership first, rather than treating an absent map
        // entry as a harmless unprivileged job.
        self::handlerFor($jobKey);
        $capabilities = self::EXECUTION_CAPABILITIES[$jobKey] ?? null;
        if ($capabilities === null) {
            throw BusinessRejection::forCode('integrations.job_capabilities_missing', sprintf('job %s has no execution capability declaration', $jobKey));
        }

        return $capabilities;
    }
}
