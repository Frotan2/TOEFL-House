<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PrivacyApiController;
use Illuminate\Support\Facades\Route;

/*
 |--------------------------------------------------------------------------
 | Privacy & Consent API
 |--------------------------------------------------------------------------
 |
 | This route file is loaded inside the canonical authenticated /api/v1
 | stack. It gives the React privacy workspace one same-origin transport for
 | both reads and writes; the legacy web POST endpoints remain only as
 | compatibility adapters. Every mutation delegates to the owning privacy
 | command, which re-resolves authority, subject provenance, lifecycle
 | legality, separation of duties, idempotency and audit evidence.
 |
 */

Route::prefix('privacy')->name('api.privacy.')->group(function (): void {
    Route::get('/workspace', [PrivacyApiController::class, 'workspace'])->name('workspace');
    Route::get('/subjects/{subjectId}', [PrivacyApiController::class, 'subject'])->name('subject');
    Route::post('/purposes', [PrivacyApiController::class, 'definePurpose'])->name('purpose.define');
    Route::post('/consents', [PrivacyApiController::class, 'recordConsent'])->name('consent.record');
    Route::post('/consents/{consentId}/submit', [PrivacyApiController::class, 'submitConsent'])->name('consent.submit');
    Route::post('/consents/{consentId}/verify', [PrivacyApiController::class, 'verifyConsent'])->name('consent.verify');
    Route::post('/consents/{consentId}/activate', [PrivacyApiController::class, 'activateConsent'])->name('consent.activate');
    Route::post('/consents/{consentId}/expire', [PrivacyApiController::class, 'expireConsent'])->name('consent.expire');
    Route::post('/consents/{consentId}/revoke', [PrivacyApiController::class, 'revokeConsent'])->name('consent.revoke');
    Route::post('/consents/{consentId}/archive', [PrivacyApiController::class, 'archiveConsent'])->name('consent.archive');
    Route::post('/disclosures', [PrivacyApiController::class, 'recordDisclosure'])->name('disclosure.record');
    Route::post('/exports', [PrivacyApiController::class, 'exportSubject'])->name('export.direct');
    Route::post('/exports/bulk', [PrivacyApiController::class, 'requestExport'])->name('export.request');
    Route::post('/exports/{requestId}/approve', [PrivacyApiController::class, 'approveExport'])->name('export.approve');
    Route::post('/exports/{requestId}/execute', [PrivacyApiController::class, 'executeExport'])->name('export.execute');
});
