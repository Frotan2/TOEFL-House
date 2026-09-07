<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AccessApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('access')->name('api.access.')->group(function (): void {
    Route::get('/workspace', [AccessApiController::class, 'workspace'])->name('workspace');
    Route::post('/assignments', [AccessApiController::class, 'assign'])->name('assignment.assign');
    Route::post('/assignments/{assignmentId}/{action}', [AccessApiController::class, 'assignmentTransition'])->where('action', 'activate|revoke')->name('assignment.transition');
    Route::post('/policies/position-role', [AccessApiController::class, 'bindPositionRole'])->name('policy.bind');
    Route::post('/policies/role-permission', [AccessApiController::class, 'grantRolePermission'])->name('policy.permission');
    Route::post('/grants', [AccessApiController::class, 'grant'])->name('grant');
    Route::post('/grants/org-wide', [AccessApiController::class, 'requestOrgWideGrant'])->name('grant.request');
    Route::post('/grants/org-wide/{requestId}/{action}', [AccessApiController::class, 'orgWideApproval'])->where('action', 'approve|execute')->name('grant.org-wide.transition');
    Route::post('/grants/{grantId}/revoke', [AccessApiController::class, 'revokeGrant'])->name('grant.revoke');
    Route::post('/delegations', [AccessApiController::class, 'delegate'])->name('delegation.create');
    Route::post('/delegations/{delegationId}/revoke', [AccessApiController::class, 'revokeDelegation'])->name('delegation.revoke');
});
