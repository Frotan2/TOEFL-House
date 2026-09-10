<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DocumentsApiController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Documents & Evidence API
 |--------------------------------------------------------------------------
 |
 | This route file is loaded inside the canonical authenticated /api/v1
 | stack. Keep document commands behind the API adapter so the React
 | workspace has a single same-origin transport; legacy web POST endpoints
 | remain only as compatibility adapters for their existing forms.
 |
 */

Route::prefix('documents')->name('api.documents.')->group(function (): void {
    Route::get('/', [DocumentsApiController::class, 'workspace'])->name('workspace');
    Route::post('/classifications', [DocumentsApiController::class, 'defineClassification'])->name('classification.define');
    Route::post('/retention-rules', [DocumentsApiController::class, 'defineRetentionRule'])->name('retention.rule.define');
    Route::post('/', [DocumentsApiController::class, 'register'])->name('register');
    Route::get('/{documentId}/history', [DocumentsApiController::class, 'history'])->name('history');
    Route::post('/{documentId}/submit', [DocumentsApiController::class, 'submit'])->name('submit');
    Route::post('/{documentId}/verify', [DocumentsApiController::class, 'verify'])->name('verify');
    Route::post('/{documentId}/activate', [DocumentsApiController::class, 'activate'])->name('activate');
    Route::post('/{documentId}/expire', [DocumentsApiController::class, 'expire'])->name('expire');
    Route::post('/{documentId}/archive', [DocumentsApiController::class, 'archive'])->name('archive');
    Route::post('/{documentId}/retention', [DocumentsApiController::class, 'decideRetention'])->name('retention.decide');
});
