<?php

use App\Modules\Integrations\Commands\EnqueueJobRun;
use App\Modules\Integrations\Commands\ProcessJobRun;
use App\Modules\Integrations\Commands\RegisterJob;
use App\Modules\Integrations\Commands\ReplayConsumerReceipt;
use App\Modules\Integrations\Domain\JobCatalog;
use App\Modules\Integrations\Models\JobRun;
use App\Modules\Integrations\Models\JobSchedule;
use App\Modules\Outbox\Models\ConsumerReceipt;
use App\Modules\Integrations\Queries\IntegrationHealth;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Commands
|--------------------------------------------------------------------------
|
| Durable integration work is entered through an explicit scheduler/operator
| identity. There is no implicit system actor: an unset run-by identity fails
| before a JobRun can be enqueued.
|
*/
Artisan::command('integrations:install-core-schedules {--run-by=}', function (): int {
    $runBy = trim((string) ($this->option('run-by') ?: config('integrations.scheduler_run_by', '')));
    if ($runBy === '') {
        $this->error('INTEGRATIONS_SCHEDULER_RUN_BY or --run-by is required; no fake system actor is permitted.');

        return 1;
    }

    $actor = new Actor($runBy, 'Integration Scheduler');
    foreach (JobCatalog::keys() as $jobKey) {
        if (JobSchedule::query()->where('job_key', $jobKey)->exists()) {
            $this->line($jobKey.': already registered');

            continue;
        }
        $result = app(RegisterJob::class)->register($actor, $jobKey, $jobKey, '* * * * *', 'console-core-schedule-'.$jobKey);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
    }

    return 0;
})->purpose('Register the allowlisted core integration schedules under an explicit durable actor');

Artisan::command('integrations:run {jobKey} {--run-by=}', function (string $jobKey): int {
    $runBy = trim((string) ($this->option('run-by') ?: config('integrations.scheduler_run_by', '')));
    if ($runBy === '') {
        $this->error('INTEGRATIONS_SCHEDULER_RUN_BY or --run-by is required; no fake system actor is permitted.');

        return 1;
    }

    $actor = new Actor($runBy, 'Integration Scheduler');
    $runKey = $jobKey.':'.now()->format('YmdHi');
    $enqueued = app(EnqueueJobRun::class)->enqueue($actor, $jobKey, $runKey, 'console-'.$jobKey.'-'.$runKey);
    $run = JobRun::query()->whereKey($enqueued['run_id'])->firstOrFail();
    $result = app(ProcessJobRun::class)->process($actor, $run, 'console-process-'.$run->id);
    $this->line(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Enqueue and process one idempotent integration job occurrence under an explicit durable actor');

Artisan::command('integrations:health {--run-by=}', function (): int {
    $runBy = trim((string) ($this->option('run-by') ?: config('integrations.scheduler_run_by', '')));
    if ($runBy === '') {
        $this->error('INTEGRATIONS_SCHEDULER_RUN_BY or --run-by is required; no fake system actor is permitted.');

        return 1;
    }

    $actor = new Actor($runBy, 'Integration Monitor');
    $outcome = app(AccessDecision::class)->decide($actor, 'integrations.process', null);
    if (! $outcome->allowed) {
        $this->error('integrations.process capability is required for health inspection.');

        return 1;
    }

    $snapshot = app(IntegrationHealth::class)->snapshot();
    $this->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $snapshot['status'] === 'critical' ? 2 : 0;
})->purpose('Read-only integration and outbox health snapshot for operational recovery');

Artisan::command('integrations:replay-consumer {receiptId} {--run-by=}', function (string $receiptId): int {
    $runBy = trim((string) ($this->option('run-by') ?: config('integrations.scheduler_run_by', '')));
    if ($runBy === '') {
        $this->error('INTEGRATIONS_SCHEDULER_RUN_BY or --run-by is required; no fake system actor is permitted.');

        return 1;
    }

    $actor = new Actor($runBy, 'Integration Recovery');
    $receipt = ConsumerReceipt::query()->find($receiptId);
    if ($receipt === null) {
        $this->error('consumer receipt not found');

        return 1;
    }

    $result = app(ReplayConsumerReceipt::class)->replay($actor, $receipt, 'console-consumer-replay-'.$receipt->id.'-'.now()->format('YmdHisv'));
    $this->line(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Replay one dead-letter consumer receipt under an explicit review actor');

// These are registration points only. Runtime scheduler/process supervision
// still belongs to deployment operations; an unset identity fails closed.
Schedule::command('integrations:run outbox.relay')->everyMinute()->withoutOverlapping();
Schedule::command('integrations:run integrations.retry_sweep')->everyMinute()->withoutOverlapping();
