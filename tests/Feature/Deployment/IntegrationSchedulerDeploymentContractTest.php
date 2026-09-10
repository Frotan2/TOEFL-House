<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The integration schedule is only real when a host can execute it against the
 * active release. Exercise the portable wrapper without a live scheduler or
 * database: a fake PHP binary records the cwd and argv the timer/cron path
 * would receive.
 */
final class IntegrationSchedulerDeploymentContractTest extends TestCase
{
    public function test_scheduler_wrapper_executes_the_artisan_schedule_from_the_current_release(): void
    {
        $root = sys_get_temp_dir().'/toefl-scheduler-'.bin2hex(random_bytes(6));
        $release = $root.'/releases/20260910090000';
        $fakePhp = $root.'/fake-php';
        $cwdFile = $root.'/cwd';
        $argumentsFile = $root.'/arguments';

        try {
            $this->assertTrue(mkdir($release.'/deploy', 0777, true));
            $this->assertTrue(copy(base_path('deploy/schedule.sh'), $release.'/deploy/schedule.sh'));
            file_put_contents($release.'/artisan', '<?php // fake artisan entrypoint');
            $this->assertTrue(symlink($release, $root.'/current'));
            file_put_contents($fakePhp, <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "$(pwd -P)" > "$SCHEDULER_CWD"
printf '%s\n' "$*" > "$SCHEDULER_ARGUMENTS"
BASH);
            chmod($fakePhp, 0755);

            $result = $this->runScheduler($root, $fakePhp, $cwdFile, $argumentsFile);

            $this->assertSame(0, $result['exit'], $result['output']);
            $this->assertSame($release, trim((string) file_get_contents($cwdFile)));
            $this->assertSame('artisan integrations:tick --no-interaction', trim((string) file_get_contents($argumentsFile)));
        } finally {
            exec('rm -rf '.escapeshellarg($root));
        }
    }

    public function test_scheduler_wrapper_fails_loudly_when_no_active_release_exists(): void
    {
        $root = sys_get_temp_dir().'/toefl-scheduler-missing-'.bin2hex(random_bytes(6));

        try {
            $this->assertTrue(mkdir($root, 0777, true));
            $output = [];
            $exit = 0;
            exec(
                'DEPLOY_ROOT='.escapeshellarg($root).' bash '.escapeshellarg(base_path('deploy/schedule.sh')).' 2>&1',
                $output,
                $exit,
            );

            $this->assertSame(1, $exit);
            $this->assertStringContainsString('current release directory is unavailable', implode("\n", $output));
        } finally {
            exec('rm -rf '.escapeshellarg($root));
        }
    }

    public function test_systemd_timer_and_runbook_use_the_same_current_release_entrypoint(): void
    {
        $service = (string) file_get_contents(base_path('deploy/systemd/toefl-house-scheduler.service'));
        $timer = (string) file_get_contents(base_path('deploy/systemd/toefl-house-scheduler.timer'));
        $runbook = (string) file_get_contents(base_path('docs/operations/production-deployment.md'));
        $traceability = (string) file_get_contents(base_path('docs/15-REQUIREMENT-TRACEABILITY.md'));

        $this->assertTrue(is_executable(base_path('deploy/schedule.sh')));
        $this->assertStringContainsString('ExecStart=/var/www/toefl-house/current/deploy/schedule.sh', $service);
        $this->assertStringContainsString('ConditionPathExists=/var/www/toefl-house/current/artisan', $service);
        $this->assertStringContainsString('OnCalendar=*-*-* *:*:00', $timer);
        $this->assertStringContainsString('Persistent=true', $timer);
        $this->assertStringContainsString('INTEGRATIONS_SCHEDULER_RUN_BY=<durable-person-uuid>', $runbook);
        $this->assertStringContainsString('toefl-house-scheduler.timer', $runbook);
        $this->assertStringContainsString('current/deploy/schedule.sh', $runbook);
        $this->assertStringContainsString('supervised relay entrypoint fails closed', $traceability);
        $this->assertStringNotContainsString('live relay intentionally not enabled', $traceability);
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function runScheduler(string $root, string $fakePhp, string $cwdFile, string $argumentsFile): array
    {
        $output = [];
        $exit = 0;
        $command = 'DEPLOY_ROOT='.escapeshellarg($root)
            .' PHP_BIN='.escapeshellarg($fakePhp)
            .' SCHEDULER_CWD='.escapeshellarg($cwdFile)
            .' SCHEDULER_ARGUMENTS='.escapeshellarg($argumentsFile)
            .' bash '.escapeshellarg($root.'/current/deploy/schedule.sh').' 2>&1';
        exec($command, $output, $exit);

        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }
}
