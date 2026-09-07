<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Production health/readiness probe (The TOEFL House).
 *
 * `/up` (framework liveness) only proves the app boots. This endpoint proves
 * the deployment is actually operable: the database is reachable and the
 * runtime configuration is valid, and (in production) the built Vite manifest
 * exists and is valid JSON. It returns 200 when healthy and 503 when a critical
 * dependency is down, so an orchestrator (systemd, nginx, a
 * load balancer, or a deploy script) can gate traffic on it. The body is
 * deliberately minimal and never leaks secrets or connection details.
 */
final class HealthController extends Controller
{
    public function __invoke(): Response
    {
        $checks = [
            'database' => 'ok',
            'application_key' => 'ok',
            'frontend_build' => 'not_required',
        ];
        $healthy = true;

        try {
            DB::connection()->select('select 1');
        } catch (\Throwable) {
            $checks['database'] = 'error';
            $healthy = false;
        }

        if (strlen((string) config('app.key')) < 32) {
            $checks['application_key'] = 'error';
            $healthy = false;
        }

        if (app()->environment('production')) {
            $checks['frontend_build'] = 'ok';
            $manifest = public_path('build/manifest.json');
            try {
                $contents = is_file($manifest) ? file_get_contents($manifest) : false;
                if ($contents === false || trim($contents) === '') {
                    throw new RuntimeException('frontend manifest unavailable');
                }
                json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $checks['frontend_build'] = 'error';
                $healthy = false;
            }
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'service' => 'The TOEFL House',
            'environment' => (string) config('app.env'),
            'checks' => $checks,
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
