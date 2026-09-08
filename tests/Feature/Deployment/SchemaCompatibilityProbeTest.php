<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The deployment preflight reads live schema state through
 * deploy/schema-compatibility.sh, so that script is executed here against the
 * same PostgreSQL database this suite runs on: a real psql round-trip, not a
 * static read of shell text.
 *
 * This coverage exists because of a specific historical defect: the deploy
 * script used to obtain the migration count through `php artisan tinker`, a
 * package that is not (and must never be) a production dependency, so every
 * deployment died with "unable to read current migration state before
 * deployment" while the application itself was perfectly healthy. The probe is
 * therefore required to (a) never depend on the release's vendor tree, and
 * (b) never report "compatible" when it could not actually look.
 */
final class SchemaCompatibilityProbeTest extends TestCase
{
    private string $probe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probe = base_path('deploy/schema-compatibility.sh');
    }

    public function test_it_reports_the_applied_migration_count_from_the_live_database(): void
    {
        $result = $this->runProbe(['--count']);

        $this->assertSame(0, $result['exit'], "probe failed: {$result['stderr']}");
        $this->assertSame(
            (string) DB::table('migrations')->count(),
            trim($result['stdout']),
            'the deploy script reads schema advancement through this exact number'
        );
    }

    public function test_it_accepts_a_release_that_ships_every_applied_migration(): void
    {
        $result = $this->runProbe(['--check', base_path()]);

        $this->assertSame(0, $result['exit'], "unexpected incompatibility: {$result['stdout']} {$result['stderr']}");
    }

    public function test_it_refuses_a_release_that_is_missing_an_applied_migration(): void
    {
        $target = sys_get_temp_dir().'/toefl-house-stale-release-'.uniqid('', true);

        try {
            File::copyDirectory(base_path('database/migrations'), "{$target}/database/migrations");

            // Drop the earliest applied migration: the live database has it, the
            // release does not, so an application-only rollback must be refused.
            $applied = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
            $this->assertNotEmpty($applied, 'this test needs a migrated database');
            $removed = (string) $applied[0];
            File::delete("{$target}/database/migrations/{$removed}.php");

            $result = $this->runProbe(['--check', $target]);

            $this->assertSame(1, $result['exit'], "expected refusal, got: {$result['stdout']} {$result['stderr']}");
            $this->assertStringContainsString($removed, $result['stdout']);
        } finally {
            File::deleteDirectory($target);
        }
    }

    public function test_it_reports_unverifiable_instead_of_compatible_when_the_database_cannot_be_reached(): void
    {
        // Exit 2 (not 0) is what makes a rollback refuse to proceed. A probe that
        // swallowed the connection error would look "compatible" and silently
        // point an older release at a newer schema.
        foreach (['--count', '--check'] as $mode) {
            $result = $this->runProbe(
                $mode === '--check' ? [$mode, base_path()] : [$mode],
                ['DB_PORT' => '1']
            );

            $this->assertSame(2, $result['exit'], "{$mode} must fail loudly: {$result['stdout']}");
            $this->assertStringContainsString('cannot query', $result['stderr']);
        }
    }

    public function test_the_deploy_script_never_goes_through_the_tinker_package(): void
    {
        // The historical defect: deploy.sh read schema state with
        // `php artisan tinker`, which a `composer install --no-dev` production
        // release does not contain. Keep both halves of that pinned.
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));
        $this->assertStringNotContainsString('tinker --execute', $deploy);
        $this->assertStringContainsString('schema-compatibility.sh', $deploy);

        $composer = (array) json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertArrayNotHasKey('laravel/tinker', (array) ($composer['require'] ?? []));
        $this->assertArrayNotHasKey('laravel/tinker', (array) ($composer['require-dev'] ?? []));
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<string, string>  $overrides
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runProbe(array $args, array $overrides = []): array
    {
        $connection = config('database.connections.pgsql');

        $env = [
            'DB_HOST' => (string) $connection['host'],
            'DB_PORT' => (string) $connection['port'],
            'DB_NAME' => (string) $connection['database'],
            'DB_USER' => (string) $connection['username'],
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
            // Same override deploy/backup.sh offers: the client is an environment
            // prerequisite, so the suite must not assume it lives under one path.
            'PSQL_BIN' => (string) (getenv('PSQL_BIN') ?: 'psql'),
        ];

        $env = array_merge($env, $overrides);

        $prefix = '';
        foreach ($env as $key => $value) {
            $prefix .= sprintf('%s=%s ', $key, escapeshellarg($value));
        }

        // Executed directly, not through `bash <path>`: the script documents the
        // first form, so its executable bit is part of the contract under test.
        $command = $prefix.escapeshellarg($this->probe);
        foreach ($args as $arg) {
            $command .= ' '.escapeshellarg($arg);
        }

        $stdoutFile = tempnam(sys_get_temp_dir(), 'toefl-probe-out');
        $stderrFile = tempnam(sys_get_temp_dir(), 'toefl-probe-err');
        $exit = 0;

        exec("{$command} > ".escapeshellarg((string) $stdoutFile).' 2> '.escapeshellarg((string) $stderrFile), $lines, $exit);

        $result = [
            'stdout' => (string) file_get_contents((string) $stdoutFile),
            'stderr' => (string) file_get_contents((string) $stderrFile),
            'exit' => (int) $exit,
        ];

        @unlink((string) $stdoutFile);
        @unlink((string) $stderrFile);

        return $result;
    }
}
