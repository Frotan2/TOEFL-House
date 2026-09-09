<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Queries\ScopedAuditQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Legacy transport kept only for compatibility; canonical reads live in React/API. */
final class AuditController extends Controller
{
    public function index(): RedirectResponse
    {
        $this->requireOrganizationRead('governance.config', 'audit.console.index');

        return redirect()->route('governance.audit');
    }

    /** @return array<string, mixed> */
    public static function scopedEvidence(Request $request, Controller $controller): array
    {
        $query = ScopedAuditQuery::forScope(
            $controller->authorizedOrganizations('governance.config'),
            $controller->authorizedBranches('governance.config'),
        );
        return ['query' => $query, 'operation' => trim((string) $request->query('operation', ''))];
    }
}
