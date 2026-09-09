<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuditApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('audit')->name('api.audit.')->group(function (): void {
    Route::get('/workspace', [AuditApiController::class, 'workspace'])->name('workspace');
});
