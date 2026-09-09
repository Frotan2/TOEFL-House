<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Where production logs go, and why that is a deployment concern.
 *
 * `deploy.sh` keeps the 4 newest release directories and prunes the rest. Laravel's
 * default log path is `storage/logs/laravel.log` — *inside* the release — so with
 * the shipped configuration every log file is deleted as soon as its release is
 * pruned, which during an incident means the record of the failure disappears while
 * the incident is still open. Measured on 2026-09-08: two requests to the deployed
 * release during a database outage produced a 29 KB exception log, and it was
 * written into `storage/logs/` of whichever release the pool was executing (which,
 * before the reload fix, was the *previous* release — the log's location is what
 * exposed that defect).
 *
 * `LOG_PATH` is the escape hatch, so the tests here pin three things: the override
 * exists and is what Monolog actually uses, the last-resort `emergency` channel is
 * deliberately *not* redirected (it must not depend on the storage that just failed),
 * and the deployment document tells the operator to set it.
 */
final class LogLocationTest extends TestCase
{
    public function test_the_writable_channels_honour_an_explicit_log_path(): void
    {
        $config = (string) file_get_contents(base_path('config/logging.php'));

        foreach (['single', 'daily'] as $channel) {
            $this->assertMatchesRegularExpression(
                '/\''.$channel.'\' => \[\n(?:.*\n)*?.*\'path\' => env\(\'LOG_PATH\', storage_path\(\'logs\/laravel\.log\'\)\),/',
                $config,
                "the {$channel} channel must fall back to the release-local path but accept LOG_PATH"
            );
        }
    }

    public function test_the_configured_path_is_where_a_logged_line_actually_lands(): void
    {
        $dir = sys_get_temp_dir().'/toefl-house-logs-'.bin2hex(random_bytes(5));
        mkdir($dir, 0o755, true);
        $file = $dir.'/app.log';

        try {
            config()->set('logging.default', 'single');
            config()->set('logging.channels.single.path', $file);

            logger()->error('gate-d-probe: the database connection was refused', ['probe' => true]);

            $this->assertFileExists($file, 'a channel configured with an explicit path must write there');
            $contents = (string) file_get_contents($file);
            $this->assertStringContainsString('gate-d-probe: the database connection was refused', $contents);
            // `[date] <env>.<level>:` — the environment segment is whatever the run
            // declared (`testing` here, `production` in the field), so the level is
            // what this assertion can honestly fix.
            $this->assertMatchesRegularExpression('/\]\s+\w+\.ERROR: gate-d-probe/', $contents);
        } finally {
            exec('rm -rf '.escapeshellarg($dir));
        }
    }

    public function test_the_last_resort_channel_stays_local(): void
    {
        $config = (string) file_get_contents(base_path('config/logging.php'));

        $start = strpos($config, "'emergency' => [");
        $this->assertNotFalse($start, "the 'emergency' channel must still exist: it is Monolog's last resort");
        $block = substr($config, $start, strpos($config, '],', $start) - $start);

        $this->assertStringContainsString("storage_path('logs/laravel.log')", $block);
        $this->assertStringNotContainsString(
            "env('LOG_PATH'",
            $block,
            'the emergency channel must not follow LOG_PATH: it is the target used when the '
            .'configured one fails, so redirecting it defeats its purpose'
        );
        $this->assertMatchesRegularExpression(
            '/\/\/[^\n]*(last resort|must not live|just failed)[^\n]*/i',
            $block,
            'the reason has to be stated where the next reader will hit it, not only in a changelog'
        );
    }

    public function test_the_deployment_procedure_tells_the_operator_to_set_it(): void
    {
        $doc = (string) file_get_contents(base_path('docs/operations/production-deployment.md'));

        $this->assertStringContainsString(
            'LOG_PATH',
            $doc,
            'an override nobody documents is an override nobody uses; the release-pruning hazard belongs in the procedure'
        );
        $this->assertMatchesRegularExpression(
            '/logs? (are|that) .{0,80}prun|prun.{0,80}logs?/i',
            $doc,
            'the document must state the consequence: logs inside a release directory are deleted with it'
        );
    }
}
