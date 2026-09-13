<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ResourcesApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('resources')->name('api.resources.')->group(function (): void {
    Route::get('/workspace', [ResourcesApiController::class, 'workspace'])->name('workspace');
    Route::post('/books', [ResourcesApiController::class, 'addBookCopy'])->name('book.add');
    Route::post('/books/{copyId}/issue', [ResourcesApiController::class, 'issueBook'])->name('book.issue');
    Route::post('/issuances/{issuanceId}/return', [ResourcesApiController::class, 'returnBook'])->name('book.return');
    Route::post('/issuances/{issuanceId}/loss', [ResourcesApiController::class, 'reportLoss'])->name('book.loss');
    Route::post('/assets', [ResourcesApiController::class, 'registerAsset'])->name('asset.register');
    Route::post('/assets/{assetId}/custody', [ResourcesApiController::class, 'assignCustody'])->name('asset.custody.assign');
    Route::post('/assets/{assetId}/custody/release', [ResourcesApiController::class, 'releaseCustody'])->name('asset.custody.release');
    Route::post('/assets/{assetId}/disposal', [ResourcesApiController::class, 'requestDisposal'])->name('asset.disposal.request');
    Route::post('/disposals/{requestId}/approve', [ResourcesApiController::class, 'approveDisposal'])->name('asset.disposal.approve');
    Route::post('/disposals/{requestId}/withdraw', [ResourcesApiController::class, 'withdrawDisposal'])->name('asset.disposal.withdraw');
    Route::post('/disposals/{requestId}/execute', [ResourcesApiController::class, 'executeDisposal'])->name('asset.disposal.execute');
    Route::post('/work-orders', [ResourcesApiController::class, 'requestWork'])->name('work.request');
    Route::post('/work-orders/{orderId}/approve', [ResourcesApiController::class, 'approveWork'])->name('work.approve');
    Route::post('/work-orders/{orderId}/start', [ResourcesApiController::class, 'startWork'])->name('work.start');
    Route::post('/work-orders/{orderId}/complete', [ResourcesApiController::class, 'completeWork'])->name('work.complete');
    Route::post('/work-orders/{orderId}/cancel', [ResourcesApiController::class, 'cancelWork'])->name('work.cancel');
});
