<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;

final class RestoreContractTest extends TestCase
{
    public function test_restore_script_verifies_before_change_and_fails_closed_after_restore(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/../RESTORE-TOEFL-HOUSE.bat');
        self::assertIsString($source);

        self::assertStringContainsString('pg_restore.exe" --list "%DUMP%"', $source);
        self::assertStringContainsString('Type RESTORE to overwrite the live database', $source);
        self::assertStringContainsString('pg_dump.exe" --host=127.0.0.1 --port=%PG_PORT% --username=postgres --format=custom', $source);
        self::assertStringContainsString('set "SAFETY_DUMP=%BACKUP_DIR%\\pre-restore-toefl_house-%TS%.dump"', $source);
        self::assertStringContainsString('pg_restore.exe" --exit-on-error', $source);
        self::assertStringContainsString('SELECT current_database()', $source);
        self::assertStringContainsString('SELECT count(*) FROM migrations', $source);
        self::assertStringContainsString('Post-restore verification failed', $source);
        self::assertStringContainsString('if %TABLES_NUM% LEQ 0 call :fail', $source);
        self::assertStringContainsString('if %MIGRATIONS_NUM% LEQ 0 call :fail', $source);
    }
}
