<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The recovery script's own contract: refuse loudly, never touch a database
 * before the dump is known to be good, and report success as success.
 *
 * The second test below is the one that earned its place. In the 2026-09-08
 * recovery drill the restore worked — 168 tables, content digest identical to
 * the pre-loss fingerprint, application healthy — and `deploy/restore.sh` still
 * exited 1, because `pg_restore --clean --create` emits `DROP DATABASE
 * IF EXISTS <target>` followed by `CREATE DATABASE <target>`, both of which
 * error whenever the target database still exists ("cannot drop the currently
 * open database", "database ... already exists"). pg_restore ignores such
 * errors, completes the restore, and exits 1 for them. An operator running the
 * documented disaster procedure would have seen it fail after doing everything
 * right, and any automation around it (`restore.sh && restart`) would have
 * stopped dead.
 *
 * A recovery path is only usable if its exit code means what it says, so the
 * script now creates the target database itself and drops --create from the
 * pg_restore invocation. That decision is not self-evident from the code, hence
 * it is pinned here. The executable proof (drop the database, restore it,
 * compare digests, run the browser journey against the recovered data) lives in
 * docs/AUDIT-2026-09-08-GATE-EVIDENCE.md, Gate B — a full drop/restore cycle
 * cannot run inside a test suite that shares one database with the application.
 */
final class RestoreProcedureContractTest extends TestCase
{
    private function script(): string
    {
        return base_path('deploy/restore.sh');
    }

    public function test_pg_restore_is_not_asked_to_create_the_database(): void
    {
        // Comments are stripped first: the file explains at length why --create is
        // avoided, and an assertion over the raw text would trip over its own prose.
        $script = $this->codeOnly($this->script());

        $this->assertStringNotContainsString(
            '--create',
            $script,
            'restore.sh must not pass --create to pg_restore: together with --clean it '
            .'makes a fully successful restore exit 1 when the target database exists. '
            .'Database existence is handled explicitly instead.'
        );

        $this->assertMatchesRegularExpression(
            // Line continuations are joined first: the flags live on their own line.
            '/pg_restore\b[^\n]*--clean --if-exists --no-owner --no-privileges/',
            $this->joinContinuations($this->codeOnly($this->script())),
            'the restore must still be clean and idempotent (--if-exists keeps drops on an '
            .'empty target to notices) and must not copy ownership or privileges.'
        );
    }

    /** Shell line continuations collapsed onto one line. */
    private function joinContinuations(string $text): string
    {
        return (string) preg_replace('/\\\\\s*\n\s*/', ' ', $text);
    }

    public function test_a_successful_restore_exits_zero(): void
    {
        // Executed, not asserted from text: the script is run end to end against
        // stubbed client tools, because the failure this guards is an exit code
        // that only appears on the success path. The drill that proved the
        // --create behaviour then surfaced a second one — the closing message
        // interpolated $HEALTH_URL, which no version of the script ever defined,
        // so under `set -u` every restore aborted with "unbound variable" and
        // exit 1 while the database was in fact fully recovered.
        $dir = sys_get_temp_dir().'/restore-exit-'.bin2hex(random_bytes(6));
        mkdir($dir.'/bin', 0o755, true);
        mkdir($dir.'/backups', 0o755, true);
        file_put_contents($dir.'/backups/toefl_house-20260101T000000Z.dump', 'stub dump');

        // Stubs stand in for pg_restore/psql: a restore into an existing,
        // already-empty-enough database, with every existence probe answering yes.
        $this->makeStub($dir.'/bin/pg_restore', <<<'SH'
#!/usr/bin/env bash
echo "pg_restore stub: $*" >> "$STUB_LOG"
exit 0
SH);
        $this->makeStub($dir.'/bin/psql', <<<'SH'
#!/usr/bin/env bash
echo "psql stub: $*" >> "$STUB_LOG"
case "$*" in
  *"FROM pg_database"*) echo 1 ;;
  *"count(*)"*) echo "tables: 3" ;;
  *) echo ok ;;
esac
exit 0
SH);

        $env = sprintf(
            'PATH=%s:$PATH STUB_LOG=%s BACKUP_DIR=%s/backups DB_NAME=toefl_house DB_HOST=127.0.0.1 DB_PORT=5432 DB_USER=postgres PGPASSWORD=x',
            escapeshellarg($dir.'/bin'),
            escapeshellarg($dir.'/stubs.log'),
            escapeshellarg($dir)
        );

        try {
            exec(
                $env.' '.escapeshellarg($this->script()).' --latest --confirm 2>&1',
                $out,
                $code
            );
            $text = implode("\n", $out);

            $this->assertSame(0, $code, "a clean restore must report success. Output was:\n".$text);
            $this->assertStringContainsString('restore complete', $text);
            $this->assertStringContainsString('pg_restore stub:', (string) file_get_contents($dir.'/stubs.log'));
        } finally {
            $this->cleanDir($dir);
        }
    }

    private function makeStub(string $path, string $body): void
    {
        file_put_contents($path, $body."\n");
        chmod($path, 0o755);
    }

    private function cleanDir(string $dir): void
    {
        exec('rm -rf '.escapeshellarg($dir));
    }

    /** Script text with whole-line comments removed. */
    private function codeOnly(string $path): string
    {
        return (string) preg_replace('/^[ \t]*#.*\n/m', '', (string) file_get_contents($path));
    }

    public function test_the_script_creates_a_missing_target_database_itself(): void
    {
        $script = (string) file_get_contents($this->script());

        $this->assertStringContainsString('pg_database WHERE datname', $script);
        $this->assertStringContainsString('CREATE DATABASE', $script);
        $this->assertMatchesRegularExpression(
            '/die "could not create database/',
            $script,
            'a failed CREATE DATABASE must abort; restoring into a database that could not be '
            .'created would otherwise produce a partial recovery reported as a clean one.'
        );
    }

    public function test_the_database_name_used_in_sql_is_validated(): void
    {
        $script = (string) file_get_contents($this->script());

        $this->assertMatchesRegularExpression(
            '/case "\$DB_NAME" in/',
            $script,
            'the target name is interpolated into a CREATE DATABASE / pg_database query, so it '
            .'must be constrained before use.'
        );
    }

    public function test_usage_documents_the_required_confirm_argument(): void
    {
        $header = (string) implode('', array_slice(file($this->script()), 0, 30));

        $this->assertMatchesRegularExpression(
            '/Usage:\n(\s*#[^\n]*\n)+/',
            $header,
            'restore.sh must keep a usage block in its header.'
        );

        // Every documented invocation must carry --confirm: an operator who follows the header
        // exactly must not land on the refusal and conclude the script is broken.
        $usageLines = array_values(array_filter(
            array_slice(file($this->script()), 0, 30),
            static fn (string $line): bool => str_contains($line, './deploy/restore.sh')
        ));

        $this->assertNotEmpty($usageLines);
        foreach ($usageLines as $line) {
            $this->assertStringContainsString(
                '--confirm',
                $line,
                'usage line advertises a destructive command without the mandatory confirmation: '
                .trim($line)
            );
        }
    }

    public function test_a_missing_dump_is_refused_before_any_database_is_touched(): void
    {
        if (! $this->postgresToolsAvailable()) {
            $this->markTestSkipped('pg_restore is not installed in this environment.');
        }

        $dir = sys_get_temp_dir().'/restore-args-'.bin2hex(random_bytes(6));
        mkdir($dir.'/backups', 0o755, true);

        try {
            [$code, $out] = $this->runRestore($dir, ['/nonexistent.dump', '--confirm']);
            $this->assertSame(1, $code);
            $this->assertStringContainsString('backup file not found', $out);

            [$code, $out] = $this->runRestore($dir, ['--latest', '--confirm']);
            $this->assertSame(1, $code);
            $this->assertStringContainsString('no backup file given and no backup found', $out);

            // An empty backup directory plus --latest must resolve to a refusal, never to a
            // destructive command built around an empty filename.
            $this->assertStringNotContainsString('pg_restore: error: could not open file ""', $out);
        } finally {
            @rmdir($dir.'/backups');
            @rmdir($dir);
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runRestore(string $dir, array $args): array
    {
        $command = sprintf(
            'BACKUP_DIR=%s %s 2>&1',
            escapeshellarg($dir.'/backups'),
            escapeshellarg($this->script())
        );

        foreach ($args as $arg) {
            $command .= ' '.escapeshellarg($arg);
        }

        $out = [];
        $code = 0;
        exec($command, $out, $code);

        return [$code, implode("\n", $out)];
    }

    private function postgresToolsAvailable(): bool
    {
        exec('command -v pg_restore >/dev/null 2>&1 && command -v psql >/dev/null 2>&1', $out, $code);

        return $code === 0;
    }
}
