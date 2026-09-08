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

    protected function setUp(): void
    {
        parent::setUp();

        // Stub the Vite manifest for every test.
        //
        // The authenticated console routes render Blade layouts that @vite() the
        // compiled JS/CSS bundles. Resolving that directive requires
        // public/build/manifest.json, which only exists after `npm run build`.
        // The backend CI job installs node dependencies but deliberately does
        // not build the frontend (that is the frontend job's responsibility), so
        // the manifest is absent there and every console render 500s with
        // ViteManifestNotFoundException - a false failure that looks like a
        // template defect but is really a missing build artifact.
        //
        // No PHP test asserts on real built-asset markup; the console tests only
        // assert on Blade-authored markup (the #react-console mount, data-view,
        // titles). Stubbing Vite makes the @vite directive emit nothing so the
        // render is exercised identically whether or not the frontend has been
        // built, keeping the backend suite independent of the frontend build.
        $this->withoutVite();
    }
}
