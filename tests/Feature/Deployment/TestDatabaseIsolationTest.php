<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

/**
 * The test suite must not be able to reach a non-test database.
 *
 * `Tests\TestCase` uses `RefreshDatabase`, whose first act is `migrate:fresh`:
 * it drops every table in whatever database the configuration resolves to. That
 * makes the database *name* a safety boundary, not a preference — and PHPUnit's
 * `<env name="DB_DATABASE" .../>` entry does not establish one by default. An
 * `<env>` element without `force="true"` is only a default: a variable already
 * in the surrounding shell wins. Since `docs/operations/production-deployment.md`
 * instructs operators to keep `DB_DATABASE=toefl_house_prod` in a deployed
 * `.env`, anyone who has sourced that file (or copied its exports into a shell
 * to debug something) and then run the tests was one step from deleting a
 * production database, with the test suite's output giving no hint of it.
 *
 * Observed, not hypothetical: during the 2026-09-08 recovery drill a
 * `DB_DATABASE=toefl_house_prod` export in the invoking shell redirected
 * `phpunit tests/Feature/Deployment` at the drill's live production-shaped
 * database, and `migrate:fresh` emptied it — destroying the seed data the drill
 * depended on and forcing a re-seed before the drill could be redone.
 *
 * Isolation-critical values are therefore forced. Connection host, port, user
 * and password stay overridable on purpose: a test database may legitimately run
 * on another cluster, and pinning those would only break CI's service container.
 */
final class TestDatabaseIsolationTest extends TestCase
{
    /** Settings that decide which store is wiped or written to during tests. */
    private const MUST_BE_FORCED = [
        'APP_ENV',
        'DB_CONNECTION',
        'DB_DATABASE',
        'CACHE_STORE',
        'SESSION_DRIVER',
        'QUEUE_CONNECTION',
    ];

    public function test_isolation_critical_environment_entries_are_forced(): void
    {
        $document = new \DOMDocument;
        $this->assertTrue(
            $document->load(base_path('phpunit.xml')),
            'phpunit.xml must remain parseable XML.'
        );

        $forced = [];
        foreach ($document->getElementsByTagName('env') as $env) {
            $forced[$env->getAttribute('name')] = $env->getAttribute('force') === 'true';
        }

        foreach (self::MUST_BE_FORCED as $name) {
            $this->assertArrayHasKey($name, $forced, "phpunit.xml must define <env name=\"{$name}\"/>");
            $this->assertTrue(
                $forced[$name],
                "<env name=\"{$name}\"/> must carry force=\"true\". Without it an inherited shell "
                .'variable wins, and a stray DB_DATABASE export points migrate:fresh at a real database.'
            );
        }
    }

    public function test_the_forced_database_name_is_a_dedicated_test_database(): void
    {
        $xml = (string) file_get_contents(base_path('phpunit.xml'));

        $this->assertMatchesRegularExpression(
            '/<env name="DB_DATABASE" value="[^"]*test[^"]*" force="true"\/>/',
            $xml,
            'the forced test database must be named for testing; a value that matches a deployment '
            .'database name would make the guard useless.'
        );

        // A positive check only: asserting the file must not *mention* a
        // production name would fail on the comment that explains why this
        // guard exists. The guard itself is the forced value above.
    }

    /**
     * Executable proof of the precedence fix: the bootstrap must win over a value
     * already in $_SERVER, because that superglobal is the one Laravel reads first.
     */
    public function test_the_bootstrap_reasserts_forced_entries_over_the_inherited_server_array(): void
    {
        $probe = <<<'PHP'
<?php
$_SERVER['DB_DATABASE'] = 'toefl_house_prod';
$_SERVER['CACHE_STORE'] = 'database';
require __DIR__.'/bootstrap.php';
echo json_encode([
    'server' => $_SERVER['DB_DATABASE'] ?? null,
    'env' => $_ENV['DB_DATABASE'] ?? null,
    'getenv' => getenv('DB_DATABASE'),
    'cache' => $_SERVER['CACHE_STORE'] ?? null,
]), PHP_EOL;
PHP;

        $file = base_path('tests/ZzBootstrapProbeTmp.php');
        file_put_contents($file, $probe);

        try {
            exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file).' 2>&1', $out, $code);
            $this->assertSame(0, $code, implode("\n", $out));

            $decoded = json_decode(trim(implode("\n", $out)), true);
            $this->assertIsArray($decoded, 'probe output was not JSON: '.implode("\n", $out));

            foreach (['server', 'env', 'getenv'] as $channel) {
                $this->assertSame(
                    'toefl_house_test',
                    $decoded[$channel],
                    'tests/bootstrap.php must pin DB_DATABASE in the {$channel} channel; Laravel reads '
                    .'$_SERVER first, so a value that only lands in getenv()/$_ENV does not neutralise '
                    .'an inherited DB_DATABASE=toefl_house_prod export.'
                );
            }

            $this->assertSame(
                'array',
                $decoded['cache'],
                'pinning must carry the declared value itself: reading the element text instead of the '
                .'value attribute writes an empty string and blanks the setting.'
            );
        } finally {
            @unlink($file);
        }
    }

    public function test_the_guard_aborts_when_the_resolved_database_diverges_from_the_declaration(): void
    {
        $declared = TestDatabaseGuard::declaredDatabase();
        $this->assertSame(
            'toefl_house_test',
            $declared,
            'a guard that reads an empty declaration disables itself, so the parse is asserted too'
        );

        $connection = (string) config('database.default');
        $original = config("database.connections.{$connection}.database");

        try {
            config()->set("database.connections.{$connection}.database", 'toefl_house_prod');
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('migrate:fresh');

            TestDatabaseGuard::assertSafe();
        } finally {
            config()->set("database.connections.{$connection}.database", $original);
        }
    }

    public function test_the_guard_stays_quiet_when_the_resolved_database_matches_the_declaration(): void
    {
        $declared = (string) TestDatabaseGuard::declaredDatabase();
        $connection = (string) config('database.default');
        $original = config("database.connections.{$connection}.database");

        try {
            config()->set("database.connections.{$connection}.database", $declared);
            TestDatabaseGuard::assertSafe();
            $this->assertTrue(true);
        } finally {
            config()->set("database.connections.{$connection}.database", $original);
        }
    }

    public function test_connection_coordinates_stay_overridable(): void
    {
        $xml = (string) file_get_contents(base_path('phpunit.xml'));

        // These are intentionally NOT forced so that CI's service container (and a
        // developer's scratch cluster) can host the test database.
        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $name) {
            $this->assertMatchesRegularExpression(
                '/<env name="'.$name.'" value="[^"]*"\/>/',
                $xml,
                "{$name} must stay overridable from the environment, or CI cannot point the suite at its service container."
            );
        }
    }
}
