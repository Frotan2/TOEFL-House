<?php

declare(strict_types=1);

namespace Tests\Canonical\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\Canonical\CanonicalTestCase;

/**
 * One workspace shell, one React app per view, one API transport.
 *
 * Migrated from tests/Unit/Architecture/{AcademicClassConvergence,
 * StudentReactConvergence}Test, which asserted exact source substrings such as
 * "view === 'academic' ? <AcademicApp". Those broke on formatting alone: the
 * ternary was reflowed and `->name()` was chained onto the route, while the
 * behaviour never changed. They also asserted prose in
 * docs/architecture/review/2026-09-05-fourth-architecture-convergence.md, a
 * file that does not exist on this branch and whose claimed contract
 * ("Runtime migration/query/concurrency/browser/build verification remains
 * unexecuted") is now false.
 *
 * The durable intent is kept and asserted against the registered routes and
 * the real mount wiring instead of against source formatting.
 */
final class WorkspaceMountConvergenceTest extends CanonicalTestCase
{
    /** Views served by the single shared `workspace` Blade shell. */
    private const SHELL_VIEWS = [
        'academic' => '/academic',
        'students' => '/students',
        'teachers' => '/teachers',
        'crm' => '/crm',
        'management' => '/management',
    ];

    public function test_each_console_route_is_served_by_the_shared_workspace_shell(): void
    {
        foreach (self::SHELL_VIEWS as $view => $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => '/'.ltrim($r->uri(), '/') === $uri && in_array('GET', $r->methods(), true));

            $this->assertNotNull($route, "{$uri} must be routed.");

            // Route::view() stores the template under 'view' and the payload
            // passed to it under 'data'.
            $this->assertSame(
                'workspace',
                $route->defaults['view'] ?? null,
                "{$uri} must render the shared workspace shell."
            );
            $this->assertSame(
                $view,
                $route->defaults['data']['view'] ?? null,
                "{$uri} must pass view '{$view}' to the shell."
            );
        }
    }

    public function test_every_shell_view_has_exactly_one_react_app(): void
    {
        $bootstrap = (string) file_get_contents(resource_path('js/app.tsx'));

        foreach (array_keys(self::SHELL_VIEWS) as $view) {
            // Whitespace-insensitive: formatting must not break the contract,
            // but the view must still select exactly one component.
            $matches = preg_match_all(
                sprintf('/view\s*===\s*[\'"]%s[\'"]\s*\n?\s*\?\s*<(\w+)/', preg_quote($view, '/')),
                $bootstrap,
                $found
            );

            $this->assertSame(1, $matches, "View '{$view}' must select exactly one React app.");
            $this->assertStringEndsWith('App', $found[1][0]);
        }
    }

    public function test_the_bootstrap_uses_the_single_canonical_api_transport(): void
    {
        $bootstrap = (string) file_get_contents(resource_path('js/app.tsx'));

        $this->assertStringContainsString('createApiClient', $bootstrap);
        $this->assertStringContainsString("'/api/v1'", $bootstrap);

        // A second transport layer would let a console bypass the canonical
        // client's auth and error handling.
        $this->assertSame(
            0,
            preg_match('/\bnew XMLHttpRequest\b|\baxios\./', $bootstrap),
            'The workspace bootstrap must not introduce a second API transport.'
        );
    }

    public function test_the_academic_workspace_is_served_by_its_own_api_endpoint(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName())->filter()->all();

        $this->assertContains('api.academic.workspace', $names);
    }
}
