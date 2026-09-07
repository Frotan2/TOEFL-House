<?php

use App\Http\Controllers\Api\HrApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr')->name('api.hr.')->group(function (): void {
    Route::get('/workspace', [HrApiController::class, 'workspace'])->name('workspace');
    Route::post('/employ', [HrApiController::class, 'employ'])->name('employ');
    Route::post('/employments/{employmentId}/{action}', [HrApiController::class, 'employmentTransition'])
        ->where('action', 'hire|leave|suspend|reinstate|terminate')->name('employment.transition');
    Route::post('/employments/{employmentId}/leave', [HrApiController::class, 'requestLeave'])->name('leave.request');
    Route::post('/leaves/{leaveId}/decide', [HrApiController::class, 'decideLeave'])->name('leave.decide');
    Route::post('/leaves/{leaveId}/cancel', [HrApiController::class, 'cancelLeave'])->name('leave.cancel');
    Route::post('/contract-versions', [HrApiController::class, 'prepareVersion'])->name('version.prepare');
    Route::post('/contract-versions/{versionId}/rules', [HrApiController::class, 'addRule'])->name('version.rule');
    Route::post('/contract-versions/{versionId}/{action}', [HrApiController::class, 'versionTransition'])
        ->where('action', 'submit|withdraw|approve')->name('version.transition');
    Route::post('/contracts', [HrApiController::class, 'draftContract'])->name('contract.draft');
    Route::post('/contracts/{contractId}/sign', [HrApiController::class, 'contractSign'])->name('contract.sign');
    Route::post('/contracts/{contractId}/close', [HrApiController::class, 'contractClose'])->name('contract.close');
    Route::post('/scales', [HrApiController::class, 'registerScale'])->name('scale.register');
    Route::post('/scales/{scaleId}/retire', [HrApiController::class, 'retireScale'])->name('scale.retire');
});
