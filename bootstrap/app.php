<?php

use App\Http\Middleware\EnsureEmployeeSession;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Errors\DomainError;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/reporting-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/hr-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/payroll-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/identity-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/access-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/organization-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/resources-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/privacy-api.php'));
            Route::prefix('api/v1')
                ->middleware(['api', 'employee'])
                ->group(base_path('routes/audit-api.php'));

            // Canonical React governance read surfaces. Legacy controller
            // routes redirect here so Blade cannot become a second read model.
            Route::middleware('employee')->group(function (): void {
                Route::view('/governance/privacy', 'workspace', ['view' => 'privacy'])->name('governance.privacy');
                Route::view('/governance/audit', 'workspace', ['view' => 'audit'])->name('governance.audit');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);

        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'employee' => EnsureEmployeeSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->expectsJson() || str_starts_with($request->path(), 'api/'),
        );

        $exceptions->renderable(function (DomainError $error, Request $request) {
            $payload = [
                'error' => $error->errorCode(),
                'category' => $error->category(),
                'message' => $error->getMessage(),
                'correlation_id' => $error->correlationId(),
                'retryable' => $error->retryable(),
            ];
            $status = match ($error->category()) {
                DomainError::CATEGORY_VALIDATION => 422,
                DomainError::CATEGORY_AUTHORIZATION => 403,
                DomainError::CATEGORY_BUSINESS_REJECTION => 409,
                DomainError::CATEGORY_CONCURRENCY_CONFLICT => 409,
                DomainError::CATEGORY_INTEGRATION_UNKNOWN => 502,
                default => 500,
            };

            if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
                return response()->json($payload, $status);
            }

            $hasReferer = $request->headers->get('referer') !== null;
            if (! $hasReferer) {
                $target = $request->user() !== null ? route('home') : route('login');

                return redirect($target)
                    ->withInput()
                    ->with('error_code', $error->errorCode())
                    ->with('error', $error->getMessage());
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error_code', $error->errorCode())
                ->with('error', $error->getMessage());
        });
    })->create();
