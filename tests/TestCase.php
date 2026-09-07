<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base test case.
 *
 * Isolation strategy: `RefreshDatabase` migrates the schema once per test
 * process and then wraps every individual test in a database transaction that
 * is rolled back on teardown.
 *
 * This is deliberately not `DatabaseMigrations`, which replays the full
 * migration chain (185 migrations) for every single test. Both give each test
 * a pristine database; only the cost differs. PostgreSQL supports
 * transactional DDL, so tests that issue statements such as
 * `ALTER TABLE ... DISABLE TRIGGER` are still rolled back correctly.
 */
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;
}
