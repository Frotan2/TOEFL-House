<?php

declare(strict_types=1);

namespace Tests\Canonical\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\Canonical\CanonicalTestCase;

/**
 * Shared workspace shell and canonical React bootstrap contracts.
 *
 * These assertions deliberately validate routing and explicit typed view
 * resolution rather than depending on a particular JSX formatting style.
 */
final class WorkspaceMountConvergenceTest extends CanonicalTestCase
{
    private const SHELL_VIEWS = [
        'academic' => '/academic',
        'students' => '/students',
        'teachers' => '/teachers',
        'crm' => '/crm',
        'management' => '/management',
        'documents' => '/documents',
    ];

    public function test_each_console_route_is_served_by_the_shared_workspace_shell(): void
    {
        foreach (self::SHELL_VIEWS as $view => $uri) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => '/'.ltrim($r->uri(), '/') === $uri && in_array('GET', $r->methods(), true));

            $this->assertNotNull($route, "{$uri} must be routed.");
            $this->assertSame('workspace', $route->defaults['view'] ?? null, "{$uri} must render the shared workspace shell.");
            $this->assertSame($view, $route->defaults['data']['view'] ?? null, "{$uri} must pass view '{$view}' to the shell.");
        }
    }

    public function test_every_shell_view_has_explicit_typed_react_resolution(): void
    {
        $bootstrap = (string) file_get_contents(resource_path('js/app.tsx'));
        $expectedComponents = [
            'academic' => ['AcademicApp', 'AcademicSetupApp'],
            'students' => ['StudentsApp', 'StudentJourneyApp'],
            'teachers' => ['TeacherApp', 'TeacherDayApp'],
            'crm' => ['CrmApp', 'FrontOfficeApp'],
            'management' => ['ManagementApp'],
            'documents' => ['DocumentsApp'],
        ];

        $this->assertStringContainsString('switch (view as ConsoleView | null)', $bootstrap);

        foreach ($expectedComponents as $view => $components) {
            $this->assertMatchesRegularExpression(
                sprintf('/case\s+[\'"]%s[\'"]\s*:/', preg_quote($view, '/')),
                $bootstrap,
                "View '{$view}' must have an explicit switch case."
            );

            foreach ($components as $component) {
                $this->assertStringContainsString("<$component", $bootstrap, "View '{$view}' must resolve {$component}.");
            }
        }
    }

    public function test_the_bootstrap_uses_the_single_canonical_api_transport(): void
    {
        $bootstrap = (string) file_get_contents(resource_path('js/app.tsx'));

        $this->assertStringContainsString('createApiClient', $bootstrap);
        $this->assertStringContainsString("'/api/v1'", $bootstrap);
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
