<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OrganizationApiController;
use Illuminate\Support\Facades\Route;

/*
 * Organization topology (ORG-OPS-01): one fail-closed management projection
 * plus the staged four-actor governance workflow (propose -> review -> two
 * distinct owner signatures; the second signature executes the canonical
 * domain command). Every mutation is idempotent via Idempotency-Key and is
 * re-authorized server-side.
 */
Route::get('/organization/workspace', [OrganizationApiController::class, 'workspace'])->name('api.organization.workspace');
Route::post('/organization/changes', [OrganizationApiController::class, 'propose'])->name('api.organization.change.propose');
Route::post('/organization/changes/{changeId}/review', [OrganizationApiController::class, 'review'])->name('api.organization.change.review');
Route::post('/organization/changes/{changeId}/approve', [OrganizationApiController::class, 'approve'])->name('api.organization.change.approve');
Route::post('/organization/changes/{changeId}/reject', [OrganizationApiController::class, 'reject'])->name('api.organization.change.reject');
Route::post('/organization/changes/{changeId}/withdraw', [OrganizationApiController::class, 'withdraw'])->name('api.organization.change.withdraw');
