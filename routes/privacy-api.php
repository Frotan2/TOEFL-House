<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PrivacyApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('privacy')->name('api.privacy.')->group(function (): void {
    Route::get('/workspace', [PrivacyApiController::class, 'workspace'])->name('workspace');
    Route::get('/subjects/{subjectId}', [PrivacyApiController::class, 'subject'])->name('subject');
});
