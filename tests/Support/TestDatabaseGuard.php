<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses to let the suite run against a database it was not configured for.
 *
 * Tests\TestCase uses RefreshDatabase, and RefreshDatabase's first act is
 * `migrate:fresh`: it drops every table in the resolved database and replays the
 * migration chain. The database name is therefore a destructive-operation
 * boundary, and it must not be decided by whatever the invoking shell happened to
 * export, nor by a stale bootstrap/cache/config.php (a cached config makes Laravel
 * ignore .env and the test environment entries entirely, which is how a
 * `config:cache` run during an afternoon of debugging turns `php artisan test`
 * into production data loss).
 *
 * The declaration in phpunit.xml is the single source of truth. Any divergence
 * between it and the connection the application actually resolved aborts the run
 * before a migration is attempted, with the remedy in the message.
 */
final class TestDatabaseGuard
{
    private static ?string $declared = null;

    /** The database name phpunit.xml declares for the test suite, if any. */
    public static function declaredDatabase(): ?string
    {
        if (self::$declared !== null) {
            return self::$declared;
        }

        $config = dirname(__DIR__, 2).'/phpunit.xml';

        if (! is_file($config)) {
            return null;
        }

        $xml = @simplexml_load_file($config);

        if ($xml === false || ! isset($xml->php->env)) {
            return null;
        }

        foreach ($xml->php->env as $env) {
            $attributes = $env->attributes();

            if ($attributes !== null && (string) ($attributes['name'] ?? '') === 'DB_DATABASE') {
                // The declaration is an attribute, not element text. (string) $env is
                // '' here, and an empty declaration silently disables the guard — which
                // is precisely the failure mode a guard must not have.
                $value = (string) ($attributes['value'] ?? '');

                if ($value !== '') {
                    return self::$declared = $value;
                }
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException when the resolved database is not the declared test database
     */
    public static function assertSafe(string $declaredOverride = ''): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        // Only server-backed drivers can point at a real database; sqlite files and
        // :memory: are created per run and are not this guard's concern.
        if (! in_array($driver, ['pgsql', 'mysql'], true)) {
            return;
        }

        $declared = $declaredOverride !== '' ? $declaredOverride : self::declaredDatabase();

        if ($declared === null || $declared === '') {
            return;
        }

        $actual = (string) config("database.connections.{$connection}.database");

        if ($actual === $declared) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests: the "%s" connection resolves to database "%s", but phpunit.xml '
            .'declares "%s" for the suite. RefreshDatabase would run migrate:fresh against "%s" and drop '
            .'every table in it. Unset the inherited DB_DATABASE (a shell export beats phpunit.xml through '
            .'$_SERVER), or delete bootstrap/cache/config.php if the config was cached against another '
            .'environment, then run the tests again.',
            $connection,
            $actual,
            $declared,
            $actual
        ));
    }
}
