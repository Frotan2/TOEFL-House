<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Modules\Integrations\Models\JobRun;
use App\Modules\Integrations\Queries\IntegrationHealth;
use App\Support\Identifiers\RandomIdentifier;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * JobRun uses `processing`, not `running`, and a terminal/dead or expired run
 * needs the same operational visibility as a delivery/consumer failure. These
 * cases are especially important now that the scheduler is supervised.
 */
final class IntegrationHealthTest extends TestCase
{
    use BuildsActors;

    public function test_job_run_terminal_and_expired_states_are_visible_and_critical(): void
    {
        $actor = $this->personWithAuthority('integration-health-operator', []);
        JobRun::query()->create([
            'id' => RandomIdentifier::new(),
            'job_key' => 'outbox.relay',
            'run_key' => 'health-dead-letter',
            'status' => 'dead_letter',
            'attempts' => 3,
            'max_attempts' => 3,
            'run_by' => $actor->id,
            'last_error' => 'unrecoverable adapter failure',
            'finished_at' => now(),
        ]);
        JobRun::query()->create([
            'id' => RandomIdentifier::new(),
            'job_key' => 'integrations.retry_sweep',
            'run_key' => 'health-expired-processing',
            'status' => 'processing',
            'attempts' => 1,
            'max_attempts' => 3,
            'run_by' => $actor->id,
            'lease_until' => now()->subMinute(),
            'started_at' => now()->subMinutes(11),
        ]);

        $snapshot = app(IntegrationHealth::class)->snapshot();

        $this->assertSame('critical', $snapshot['status']);
        $this->assertSame(1, $snapshot['job_runs']['dead_letter']);
        $this->assertSame(1, $snapshot['job_runs']['processing']);
        $this->assertSame(1, $snapshot['job_runs']['expired_processing']);
        $this->assertArrayNotHasKey('running', $snapshot['job_runs']);
    }

    public function test_due_job_run_is_reported_as_degraded_before_it_is_processed(): void
    {
        $actor = $this->personWithAuthority('integration-health-queued', []);
        JobRun::query()->create([
            'id' => RandomIdentifier::new(),
            'job_key' => 'outbox.relay',
            'run_key' => 'health-queued',
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => 3,
            'run_by' => $actor->id,
        ]);

        $snapshot = app(IntegrationHealth::class)->snapshot();

        $this->assertSame('degraded', $snapshot['status']);
        $this->assertSame(1, $snapshot['job_runs']['queued']);
        $this->assertSame(1, $snapshot['job_runs']['due']);
    }
}
