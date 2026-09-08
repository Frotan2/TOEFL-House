<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Authorization\Actor;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Production security hardening (The TOEFL House): baseline security headers,
 * brute-force protection on sign-in, and the production health/readiness
 * probe. These verify the transport-layer controls that protect the
 * deployment; per-operation authorization is proven by the module command
 * tests.
 */
final class SecurityHardeningFeatureTest extends TestCase
{
    use BuildsActors;

    private function makeEmployee(string $username = 'security.employee', string $password = 'correct-horse-99'): UserAccount
    {
        $personId = RandomIdentifier::new();
        $person = Person::query()->create([
            'id' => $personId,
            'legal_name' => 'Security Employee',
            'date_of_birth' => '1990-01-01',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'fixture-'.$personId,
            'identity_evidence_ref' => 'evidence/fixture/'.$personId,
            'verified_by' => 'fixture-verifier',
            'verified_at' => now()->toDateTimeString(),
        ]);

        return UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $person->id,
            'username' => $username,
            'password_hash' => Hash::make($password),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    public function test_responses_carry_baseline_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security');
    }

    /**
     * The Content-Security-Policy is the header that decides whether injected
     * markup can execute, so it is asserted as a parsed policy rather than as a
     * substring: "contains script-src" passes even when script-src allows
     * 'unsafe-inline', which is the whole point of the header.
     *
     * The directive set was derived from a browser inventory of the *deployed*
     * release (login plus all 14 consoles): no inline <script>, no inline event
     * handlers, no cross-origin script, no iframe/object/embed, no blob: or data:
     * script, and bundles free of eval/new Function/document.write. That is why
     * script-src can stay at 'self' with no nonce and the policy can be enforced
     * rather than report-only. See docs/AUDIT-2026-09-08-GATE-EVIDENCE.md §Gate E.
     */
    public function test_the_content_security_policy_leaves_no_hole_in_script_execution(): void
    {
        $directives = $this->cspDirectives($this->get('/login'));

        $this->assertSame("'self'", $directives['default-src']);
        $this->assertSame(
            "'self'",
            $directives['script-src'],
            'script-src is the directive an XSS actually needs; it must allow nothing but same-origin files. '
            .'A nonce or hash scheme would be acceptable, inline or eval never is.'
        );
        $this->assertStringNotContainsString("'unsafe-inline'", $directives['script-src']);
        $this->assertStringNotContainsString("'unsafe-eval'", $directives['script-src']);
        $this->assertStringNotContainsString("'unsafe-eval'", $directives['style-src']);

        // Relaxation is confined to styles, where Vite injects a <style> element
        // and Blade uses a few style="" attributes (measured: 1 block, 6 attrs).
        $this->assertStringContainsString("'unsafe-inline'", $directives['style-src']);

        foreach (["'none'"] as $expected) {
            $this->assertSame($expected, $directives['frame-ancestors']);
            $this->assertSame($expected, $directives['object-src']);
        }
        $this->assertSame("'none'", $directives['frame-src']);
        $this->assertSame("'none'", $directives['worker-src']);
        $this->assertSame("'self'", $directives['base-uri'], 'base-uri is how a single injected <base> rewrites every relative URL on the page');
        $this->assertSame("'self'", $directives['form-action']);
        $this->assertSame("'self'", $directives['connect-src']);

        // Nothing in the measured page needs a data: image or a cross-origin
        // fetch, so the policy must not pre-authorise either "just in case".
        $this->assertSame("'self'", $directives['img-src']);
        $this->assertStringNotContainsString('data:', $directives['img-src']);
        $this->assertStringNotContainsString(
            'https:',
            implode('; ', $directives),
            'a wildcard scheme allowance silently permits every remote host on the internet'
        );
    }

    public function test_the_policy_covers_api_responses_as_well_as_pages(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Security-Policy', SecurityHeaders::CSP_PRODUCTION);
    }

    public function test_the_web_server_config_and_the_middleware_agree_on_the_policy(): void
    {
        // Two sources of truth drift, and the weaker one wins for whichever path
        // serves the response: an operator comparing headers sees "CSP is set"
        // either way. nginx also sets the headers for static assets, which never
        // reach the middleware at all.
        $conf = (string) file_get_contents(base_path('deploy/nginx/toefl-house.conf'));

        $this->assertSame(
            1,
            substr_count($conf, 'add_header Content-Security-Policy'),
            'the policy belongs in exactly one place in the server config; per-location duplicates drift'
        );

        preg_match('/add_header Content-Security-Policy "([^"]+)" always;/', $conf, $match);
        $this->assertNotFalse($match[1] ?? false, 'the web server must send the CSP with `always`, or it disappears on error responses');
        $this->assertSame(SecurityHeaders::CSP_PRODUCTION, $match[1]);
    }

    public function test_the_dev_server_carve_out_is_scoped_to_a_hot_file_in_a_non_production_environment(): void
    {
        $marker = public_path('hot');
        $environment = app()['env'];

        try {
            // No marker: strict, even outside production.
            @unlink($marker);
            $this->assertSame(
                SecurityHeaders::CSP_PRODUCTION,
                SecurityHeaders::policy()
            );

            file_put_contents($marker, 'http://127.0.0.1:4173');
            $policy = SecurityHeaders::policy();

            $this->assertStringContainsString("script-src 'self' http://127.0.0.1:4173;", $policy);
            $this->assertStringContainsString("connect-src 'self' http://127.0.0.1:4173 ws://127.0.0.1:4173;", $policy);
            $carveOut = $this->policyDirectives($policy);

            $this->assertSame("'self' http://127.0.0.1:4173", $carveOut['script-src']);
            $this->assertSame("'self' http://127.0.0.1:4173 ws://127.0.0.1:4173", $carveOut['connect-src']);
            $this->assertStringNotContainsString(
                "'unsafe-inline'",
                $carveOut['script-src'],
                'HMR is allowed by origin. The moment script-src itself is relaxed the policy stops mattering; '
                .'style-src legitimately keeps unsafe-inline because Vite injects a style element.'
            );

            // Production never carves out, marker or not: the file can be left
            // behind by a build, and a header that weakens itself on stale state is
            // not a control.
            app()['env'] = 'production';
            $this->assertSame(
                SecurityHeaders::CSP_PRODUCTION,
                SecurityHeaders::policy(),
                'a stray public/hot must not open the production policy'
            );
        } finally {
            app()['env'] = $environment;
            @unlink($marker);
        }
    }

    /** @return array<string,string> a CSP header keyed by directive name */
    private function policyDirectives(string $header): array
    {
        $directives = [];
        foreach (array_filter(explode(';', $header)) as $part) {
            [$name, $value] = array_pad(explode(' ', trim($part), 2), 2, '');
            $directives[$name] = trim($value);
        }

        return $directives;
    }

    /** @return array<string,string> the CSP of a response, keyed by directive name */
    private function cspDirectives(TestResponse $response): array
    {
        $header = (string) $response->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $header, 'the console renders 14 JavaScript-driven screens; a missing CSP header means any injected markup executes');

        $directives = $this->policyDirectives($header);

        foreach (['default-src', 'script-src', 'style-src', 'img-src', 'connect-src', 'frame-ancestors', 'base-uri', 'object-src'] as $required) {
            $this->assertArrayHasKey($required, $directives, "the policy must state {$required} explicitly; relying on default-src inheritance is how a later edit quietly opens a hole");
        }

        return $directives;
    }

    public function test_api_responses_carry_security_headers_too(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_login_is_rate_limited_after_the_per_minute_allowance(): void
    {
        $this->makeEmployee();

        // The allowance is 5 attempts per minute per (IP, username).
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['username' => 'security.employee', 'password' => 'correct-horse-99'])
                ->assertStatus(302);
        }

        // The 6th attempt from the same IP for the same username is rejected
        // before it reaches the credential check.
        $this->post('/login', ['username' => 'security.employee', 'password' => 'correct-horse-99'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_login_with_keep_me_signed_in_persists_the_recaller(): void
    {
        $this->makeEmployee();

        // The advertised "keep me signed in" must work end-to-end: the
        // guard stores its token in user_accounts.remember_token (000117)
        // and issues the recaller cookie. Without the column this was a 500.
        $withRemember = $this->post('/login', ['username' => 'security.employee', 'password' => 'correct-horse-99', 'remember' => '1']);
        $withRemember->assertRedirect('/');
        $this->assertTrue(
            collect($withRemember->headers->all('set-cookie'))
                ->contains(fn (?string $header): bool => is_string($header) && preg_match('/^remember_web_[a-f0-9]+=[^;]+/', $header) === 1),
            'the remember-enabled sign-in must issue the recaller cookie',
        );
    }

    public function test_login_without_remember_issues_no_recaller(): void
    {
        $this->makeEmployee();

        // A session-only sign-in must not create a long-lived credential.
        $withoutRemember = $this->post('/login', ['username' => 'security.employee', 'password' => 'correct-horse-99']);
        $withoutRemember->assertRedirect('/');
        $this->assertFalse(
            collect($withoutRemember->headers->all('set-cookie'))
                ->contains(fn (?string $header): bool => is_string($header) && preg_match('/^remember_web_[a-f0-9]+=[^;]+/', $header) === 1),
            'a session-only sign-in must not issue a recaller cookie',
        );
    }

    public function test_health_endpoint_reports_healthy_with_database_check(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'The TOEFL House')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.application_key', 'ok')
            ->assertJsonPath('checks.frontend_build', 'not_required');
    }

    public function test_health_endpoint_is_public_and_does_not_leak_secrets(): void
    {
        $body = $this->getJson('/health')->assertOk()->json();

        $serialized = json_encode($body, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('DB_PASSWORD', $serialized);
        $this->assertStringNotContainsString('postgres', $serialized);
        $this->assertArrayNotHasKey('version', $body['checks']);
    }

    /**
     * FINAL ADVERSARIAL ATTACK — the audit trail is the system's integrity
     * backbone, so it must be attacked directly at the SQL layer: any UPDATE
     * or DELETE on audit_events is rejected by the append-only trigger
     * (000008), not merely by application discipline.
     */
    public function test_direct_sql_tampering_with_the_audit_trail_is_rejected_by_the_schema(): void
    {
        $this->personWithAuthority('sha-auditor-1', ['finance.period']);
        $actor = new Actor('sha-auditor-1', 'Auditor');

        app(MaintainFinancialPeriod::class)->open($actor, '2027-01', '2027-01-01', '2027-01-31', 'sha-period-1');

        $row = DB::table('audit_events')->where('operation', 'finance.period.open')->first();
        $this->assertNotNull($row);

        // A rejected statement aborts the surrounding transaction, so this
        // attempt runs in its own savepoint and the assertions after it can
        // still read.
        DB::beginTransaction();
        try {
            DB::statement('UPDATE audit_events SET after_state = ? WHERE id = ?', ['{"forged":true}', $row->id]);
            $this->fail('the audit trail must be append-only');
            DB::rollBack();
        } catch (QueryException) {
            DB::rollBack();
            $this->addToAssertionCount(1);
        }

        // A rejected statement aborts the surrounding transaction, so this
        // attempt runs in its own savepoint and later reads still work.
        DB::beginTransaction();
        try {
            DB::statement('DELETE FROM audit_events WHERE id = ?', [$row->id]);
            $this->fail('the audit trail must be append-only');
            DB::rollBack();
        } catch (QueryException) {
            DB::rollBack();
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('audit_events', ['id' => $row->id, 'operation' => 'finance.period.open']);
    }
}
