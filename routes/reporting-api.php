<?php

use App\Http\Controllers\Api\ReportingApiController;
use Illuminate\Support\Facades\Route;

/*
| Reporting React boundary. This file is mounted by bootstrap/app.php inside
| the same /api/v1 + stateful employee API contract as the other SPA domains.
| It contains transport only; Reporting commands remain authoritative.
*/

Route::prefix('reporting')->name('api.reporting.')->group(function (): void {
    Route::get('/workspace', [ReportingApiController::class, 'workspace'])->name('workspace');
    Route::post('/runs', [ReportingApiController::class, 'run'])->name('run');
    Route::post('/dashboards', [ReportingApiController::class, 'createDashboard'])->name('dashboard.create');
    Route::post('/dashboards/{dashboardId}/pin', [ReportingApiController::class, 'pinDashboard'])->name('dashboard.pin');
});
