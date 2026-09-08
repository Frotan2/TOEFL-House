<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Tests\TestCase;

/**
 * The contract the deployment procedure depends on.
 *
 * `deploy.sh` decides whether a release is healthy by polling `/health`, and
 * `docs/operations/production-deployment.md` tells operators to gate traffic on it.
 * That only works if the endpoint survives the failure it exists to report, and it
 * did not. Measured against the live production deployment with PostgreSQL stopped
 * (`pg_ctl -m fast stop`, `APP_ENV=production`, `SESSION_DRIVER=database`):
 *
 *   /health -> HTTP 500, text/html, "<title>Server Error</title>"
 *   /login  -> HTTP 500
 *   /api/v1/management -> HTTP 500, {"message":"Server Error"}
 *   /up     -> HTTP 200
 *
 * `/health` returned a 500 HTML page instead of its documented 503 JSON because the
 * route sits in the `web` group, where `StartSession` opens a database-backed session
 * *before* the controller runs — so the controller's own try/catch, which is correct
 * and reports `{"status":"error","checks":{"database":"error"}}` with 503, was never
 * reached. `/up` staying at 200 is the framework's liveness probe behaving as
 * designed (it proves PHP is alive, not that the service is operable) — which is why
 * the two endpoints exist and why an orchestrator must gate on `/health`.
 *
 * The fix is that the probe must not depend on a session. These tests encode the
 * production condition (`session.driver=database` plus an unreachable default
 * connection) rather than the convenient one, because with the test default of
 * `array` the old code passed: an assertion that cannot fail is worse than none.
 */
final class HealthProbeContractTest extends TestCase
{
    private const UNREACHABLE = 'probe_unreachable';

    protected function tearDown(): void
    {
        config()->set('database.default', 'pgsql');
        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');

        parent::tearDown();
    }

    public function test_the_probe_reports_unhealthy_rather_than_failing_when_the_database_is_down(): void
    {
        $this->pointEverythingAtAnUnreachableDatabase();

        $response = $this->getJson('/health');

        $response->assertStatus(503);

        $json = $response->json();
        $this->assertSame('error', $json['status']);
        $this->assertSame('error', $json['checks']['database']);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_the_probe_answers_even_though_the_session_store_is_dead(): void
    {
        $this->pointEverythingAtAnUnreachableDatabase();

        // The regression itself: with the session middleware in the pipeline the
        // response was the framework's 500 page. A probe must answer with its own
        // contract, in a form a load balancer can read, *without* being asked for
        // JSON — most health checkers send no Accept header at all.
        $response = $this->get('/health');

        $this->assertNotSame(
            500,
            $response->getStatusCode(),
            'a health probe must never answer 500 for a dependency outage: that is indistinguishable '
            .'from "the application is broken", which is the wrong signal to act on'
        );
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringNotContainsString('Server Error', $response->getContent());
        $this->assertStringContainsString('"database":"error"', str_replace(' ', '', $response->getContent()));
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_the_probe_excludes_the_middleware_that_requires_a_working_database(): void
    {
        $route = Route::getRoutes()->getByName('health');
        $this->assertNotNull($route, '`health` is the route name the deployment script and the docs reference');

        $excluded = array_map(
            static fn (string $class): string => ltrim($class, '\\'),
            $route->excludedMiddleware()
        );

        foreach ([StartSession::class, ShareErrorsFromSession::class] as $middleware) {
            $this->assertContains(
                $middleware,
                $excluded,
                $middleware.' reads or writes the sessions table, so the probe must exclude it or the endpoint dies before the controller runs'
            );
        }
    }

    public function test_the_probe_still_reports_healthy_under_normal_conditions(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $this->assertSame('ok', $response->json('status'));
        $this->assertSame('ok', $response->json('checks.database'));
    }

    public function test_the_probe_never_leaks_connection_details(): void
    {
        $this->pointEverythingAtAnUnreachableDatabase();

        $body = $this->getJson('/health')->getContent();

        foreach (['SQLSTATE', 'PDOException', 'probe_unreachable', 'nobody', 'does_not_exist', 'vendor/'] as $secret) {
            $this->assertStringNotContainsString(
                $secret,
                (string) $body,
                "the health body is served without authentication and must not expose {$secret}"
            );
        }
    }

    /**
     * Production's shape: database sessions, database cache, and a default
     * connection that cannot answer. Port 1 is closed on every host, and the connect
     * fails immediately, so this neither hangs nor depends on the sandbox.
     */
    private function pointEverythingAtAnUnreachableDatabase(): void
    {
        config()->set('database.connections.'.self::UNREACHABLE, [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'does_not_exist',
            'username' => 'nobody',
            'password' => '',
            'charset' => 'UTF8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        config()->set('database.default', self::UNREACHABLE);
        config()->set('session.driver', 'database');
        config()->set('cache.default', 'database');
    }
}
