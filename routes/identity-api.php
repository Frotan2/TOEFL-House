<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IdentityApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('identity')->name('api.identity.')->group(function (): void {
    Route::get('/accounts', [IdentityApiController::class, 'accounts'])->name('accounts');
    Route::post('/accounts/{accountId}/deactivate', [IdentityApiController::class, 'deactivate'])->name('deactivate');
});
