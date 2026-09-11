<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Commands;

use App\Modules\Integrations\Domain\JobCatalog;
use App\Modules\Integrations\Domain\ScheduleExpression;
use App\Modules\Integrations\Models\JobRun;
use App\Modules\Integrations\Models\JobSchedule;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use DateTimeInterface;

/**
 * Console/scheduler adapter for one catalogued job. It keeps schedule state,
 * cron timing, and the complete execution authority set ahead of enqueueing a
 * durable occurrence, while EnqueueJobRun and ProcessJobRun repeat their own
 * authoritative checks under transaction locks.
 */
final class RunScheduledJob
{
    public function __construct(
        private readonly AccessDecision $access,
        private readonly EnqueueJobRun $enqueue,
        private readonly ProcessJobRun $process,
    ) {}

    /**
     * Check all work the timer may enter before it starts any of it. A bad
     * scheduler identity or a corrupt enabled schedule is a service failure,
     * not a partial tick that silently processes only one job kind.
     *
     * @param  list<string>  $jobKeys
     */
    public function preflight(Actor $actor, array $jobKeys): void
    {
        foreach ($jobKeys as $jobKey) {
            $this->requireExecutionCapabilities($actor, $jobKey);
            $schedule = $this->scheduleFor($jobKey);
            if ($schedule->enabled) {
                ScheduleExpression::normalize((string) $schedule->schedule_expr);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Actor $actor, string $jobKey, DateTimeInterface $at): array
    {
        $this->requireExecutionCapabilities($actor, $jobKey);
        $schedule = $this->scheduleFor($jobKey);
        if (! $schedule->enabled) {
            return ['job_key' => $jobKey, 'status' => 'skipped', 'reason' => 'disabled'];
        }
        if (! ScheduleExpression::isDue((string) $schedule->schedule_expr, $at)) {
            return ['job_key' => $jobKey, 'status' => 'skipped', 'reason' => 'not_due'];
        }

        $runKey = $jobKey.':'.$at->format('YmdHi');
        $enqueued = $this->enqueue->enqueue($actor, $jobKey, $runKey, 'console-'.$jobKey.'-'.$runKey);
        $run = JobRun::query()->whereKey($enqueued['run_id'])->firstOrFail();
        $result = $this->process->process($actor, $run, 'console-process-'.$run->id);

        return ['job_key' => $jobKey, ...$result];
    }

    private function scheduleFor(string $jobKey): JobSchedule
    {
        // This also rejects a crafted key before any schedule lookup can turn
        // it into a durable JobRun.
        JobCatalog::handlerFor($jobKey);
        /** @var JobSchedule|null $schedule */
        $schedule = JobSchedule::query()->where('job_key', $jobKey)->first();
        if ($schedule === null) {
            throw BusinessRejection::forCode('integrations.job_unscheduled', 'only registered schedules enqueue runs');
        }

        return $schedule;
    }

    private function requireExecutionCapabilities(Actor $actor, string $jobKey): void
    {
        $capabilities = array_unique(array_merge(
            [EnqueueJobRun::CAPABILITY],
            JobCatalog::executionCapabilitiesFor($jobKey),
        ));
        foreach ($capabilities as $capability) {
            $outcome = $this->access->decide($actor, $capability, null);
            if (! $outcome->allowed) {
                throw AuthorizationDenied::forCode(
                    'integrations.job_execution_denied',
                    $capability.' capability is required for scheduled execution: '.$outcome->reason,
                );
            }
        }
    }
}
