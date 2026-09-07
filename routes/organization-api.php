<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OrganizationApiController;
use Illuminate\Support\Facades\Route;

Route::get('/organization/workspace', [OrganizationApiController::class, 'workspace'])->name('api.organization.workspace');
