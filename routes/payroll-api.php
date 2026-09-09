<?php

use App\Http\Controllers\Api\PayrollPeriodApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('payroll')->name('api.payroll.period.')->group(function (): void {
    Route::post('/periods', [PayrollPeriodApiController::class, 'open'])->name('open');
    Route::post('/periods/{periodId}/close', [PayrollPeriodApiController::class, 'close'])->name('close');
});
