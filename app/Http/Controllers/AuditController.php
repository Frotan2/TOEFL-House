<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/** Legacy transport only; canonical audit reads live in the React/API surface. */
final class AuditController extends Controller
{
    public function index(): RedirectResponse
    {
        $this->requireOrganizationRead('governance.config', 'audit.console.index');

        return redirect()->route('governance.audit');
    }
}
