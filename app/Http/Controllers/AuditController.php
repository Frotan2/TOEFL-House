<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/** Legacy transport kept only for compatibility; canonical audit reads live in React/API. */
final class AuditController extends Controller
{
    public function index(): RedirectResponse
    {
        $this->requireOrganizationRead('governance.config', 'audit.console.index');
        return redirect()->route('governance.audit');
    }
}
