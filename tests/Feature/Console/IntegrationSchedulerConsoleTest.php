<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Integrations\Commands\RegisterJob;
use App\Modules\Integrations\Models\JobSchedule;
use App\Support\Authorization\Actor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * The scheduled integration path is intentionally a Laravel console adapter,
 * not an implicit system actor. Exercise the adapter because a domain-only
 * test cannot establish that the framework tick enters the durable JobRun path.
 */
final class IntegrationSchedulerConsoleTest extends TestCase
{
    use BuildsActors;

    private Actor $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = $this->grantedActor('integration-scheduler', ['integrations.jobs', 'integrations.process']);
        config(['integrations.scheduler_run_by' => $this->scheduler->actorId]);
        app(RegisterJob::class)->register(
            $this->scheduler,
            'integrations.retry_sweep',
            'Integration Retry Sweep',
            '* * * * *',
            'scheduler-register-retry',
        );
    }

    public function test_laravel_registers_the_locked_integration_tick(): void
    {
        $result = $this->callArtisan('schedule:list');

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('integrations:tick', $result['output']);
    }

    public function test_due_schedule_creates_and_processes_a_durable_run_under_the_configured_actor(): void
    {
        $result = $this->callArtisan('integrations:run', ['jobKey' => 'integrations.retry_sweep']);

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('"status":"succeeded"', $result['output']);
        $this->assertDatabaseHas('job_runs', [
            'job_key' => 'integrations.retry_sweep',
            'run_by' => 'integration-scheduler',
            'status' => 'succeeded',
            'attempts' => 1,
        ]);
    }

    public function test_tick_refuses_a_partial_catalog_before_any_job_is_enqueued(): void
    {
        $result = $this->callArtisan('integrations:tick');

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('only registered schedules enqueue runs', $result['output']);
        $this->assertDatabaseCount('job_runs', 0);
    }

    public function test_tick_preflights_the_catalog_then_processes_each_due_schedule(): void
    {
        app(RegisterJob::class)->register(
            $this->scheduler,
            'outbox.relay',
            'Outbox Relay',
            '* * * * *',
            'scheduler-register-outbox-due',
        );

        $result = $this->callArtisan('integrations:tick');

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('"status":"completed"', $result['output']);
        $this->assertDatabaseHas('job_runs', [
            'job_key' => 'outbox.relay',
            'run_by' => 'integration-scheduler',
            'status' => 'succeeded',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('job_runs', [
            'job_key' => 'integrations.retry_sweep',
            'run_by' => 'integration-scheduler',
            'status' => 'succeeded',
            'attempts' => 1,
        ]);
    }

    public function test_disabled_or_not_due_schedules_do_not_enqueue_a_run(): void
    {
        $schedule = JobSchedule::query()->where('job_key', 'integrations.retry_sweep')->firstOrFail();
        app(RegisterJob::class)->toggle($this->scheduler, $schedule, false, 'scheduler-disable-retry');

        $disabled = $this->callArtisan('integrations:run', ['jobKey' => 'integrations.retry_sweep']);
        $this->assertSame(0, $disabled['exit'], $disabled['output']);
        $this->assertStringContainsString('"reason":"disabled"', $disabled['output']);
        $this->assertDatabaseCount('job_runs', 0);

        app(RegisterJob::class)->register(
            $this->scheduler,
            'outbox.relay',
            'Outbox Relay',
            ((((int) now()->format('i')) + 30) % 60).' * * * *',
            'scheduler-register-outbox',
        );
        $notDue = $this->callArtisan('integrations:run', ['jobKey' => 'outbox.relay']);

        $this->assertSame(0, $notDue['exit'], $notDue['output']);
        $this->assertStringContainsString('"reason":"not_due"', $notDue['output']);
        $this->assertDatabaseCount('job_runs', 0);
    }

    public function test_under_authorized_scheduler_fails_before_enqueuing_a_privileged_job(): void
    {
        $jobsOnly = $this->grantedActor('integration-scheduler-jobs-only', ['integrations.jobs']);
        config(['integrations.scheduler_run_by' => $jobsOnly->actorId]);

        $result = $this->callArtisan('integrations:tick');

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('integrations.process capability is required', $result['output']);
        $this->assertDatabaseCount('job_runs', 0);
    }

    public function test_overlapping_tick_is_skipped_without_creating_another_run(): void
    {
        $lock = Cache::lock('integrations:tick', 600);
        $this->assertTrue($lock->get());

        try {
            $result = $this->callArtisan('integrations:tick');
        } finally {
            $lock->release();
        }

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('"reason":"overlapping_tick"', $result['output']);
        $this->assertDatabaseCount('job_runs', 0);
    }

    public function test_missing_scheduler_identity_fails_before_schedule_or_run_lookup(): void
    {
        config(['integrations.scheduler_run_by' => null]);

        $result = $this->callArtisan('integrations:tick');

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('INTEGRATIONS_SCHEDULER_RUN_BY', $result['output']);
        $this->assertDatabaseCount('job_runs', 0);
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{exit: int, output: string}
     */
    private function callArtisan(string $command, array $parameters = []): array
    {
        $output = new BufferedOutput;
        $exit = Artisan::call($command, $parameters, $output);

        return ['exit' => $exit, 'output' => $output->fetch()];
    }
}
