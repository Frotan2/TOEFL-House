<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Page-render smoke coverage for the authenticated console routes.
 *
 * These routes render Blade templates that the API feature tests never touch.
 * A template-level error (for example an unbalanced parenthesis in a title
 * expression) turns every console into an HTTP 500 while the entire API suite
 * stays green, so the render itself is asserted here.
 *
 * The session must be authenticated: an unauthenticated request is redirected
 * before the view is ever compiled, which would make these assertions vacuous.
 */
final class WorkspacePageRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These routes render Blade templates that @vite the built bundles.
        // Without public/build/manifest.json the views do NOT 500 on current
        // Laravel: they return 200 with an empty shell — no <script>/bundle
        // tags at all — so every assertion below (status, Blade title, mount
        // div) would still pass while a browser loads a permanently empty
        // console. The guard therefore keeps the tests honest rather than
        // green over an unbuilt UI:
        //   - local/developer checkout without a build: explicit skip with the
        //     command to run;
        //   - CI (CI=true, as on GitHub Actions): fail loudly. A green CI run
        //     must mean the rendered console was genuinely exercised; a
        //     workflow that forgets the production build cannot silently
        //     drop this coverage.
        if (! is_file(public_path('build/manifest.json'))) {
            if (getenv('CI') !== false && getenv('CI') !== '' && getenv('CI') !== 'false') {
                $this->fail('Frontend assets are not built in CI (public/build/manifest.json missing); run the canonical `npm run build` before the PHP suite so console page rendering is actually verified.');
            }
            $this->markTestSkipped('Frontend assets are not built; run `npm run build` first.');
        }

        $person = Person::query()->create([
            'id' => RandomIdentifier::new(),
            'legal_name' => 'Page Render Probe',
            'date_of_birth' => '1980-01-01',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'page-render-probe',
            'identity_evidence_ref' => 'evidence/page-render-probe',
            'verified_by' => 'page-render-verifier',
            'verified_at' => now()->toDateTimeString(),
        ]);

        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $person->id,
            'username' => 'page.render.probe',
            'password_hash' => Hash::make('page-render-pw-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);

        $this->post('/login', [
            'username' => 'page.render.probe',
            'password' => 'page-render-pw-1',
        ])->assertRedirect('/');
    }

    /** @return list<array{0: string}> */
    public static function consoleRoutes(): array
    {
        return [
            ['/workspace'],
            ['/students'],
            ['/academic'],
            ['/teachers'],
            ['/crm'],
            ['/management'],
            ['/reporting'],
            ['/finance'],
            ['/hr'],
            ['/payroll'],
            ['/placement'],
            ['/access'],
            ['/organization'],
            ['/identity'],
            ['/documents'],
        ];
    }

    #[DataProvider('consoleRoutes')]
    public function test_console_route_renders_without_a_server_error(string $route): void
    {
        $response = $this->get($route);

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "{$route} returned {$response->getStatusCode()}; the console template failed to render."
        );
    }

    public function test_workspace_renders_its_title_and_mount_point(): void
    {
        $this->get('/workspace')
            ->assertOk()
            ->assertSee('Employee Workspace', false)
            ->assertSee('csrf-token', false);
    }

    public function test_documents_renders_the_canonical_workspace_mount_and_title(): void
    {
        $this->get('/documents')
            ->assertOk()
            ->assertSee('Documents &amp; Evidence — The TOEFL House', false)
            ->assertSee('data-view="documents"', false);
    }

    public function test_health_endpoint_reports_database_reachable(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok');
    }
}
