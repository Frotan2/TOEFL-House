<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The CORS posture is same-origin only, and it has to fail closed.
 *
 * Before `config/cors.php` existed this was true by omission: `HandleCors` is
 * installed globally by `bootstrap/app.php`, found no `cors.paths`, and therefore
 * emitted no header. A missing file and a deliberate decision look identical from
 * the outside and behave differently the moment someone publishes a config — so
 * the file now exists, and these tests pin what it guarantees.
 *
 * The ordering is what makes the first assertion meaningful. Every case that
 * expects "no header" is followed by one that expects a header, so a config the
 * middleware never reads cannot pass by returning nothing everywhere.
 */
final class ApiCorsPostureTest extends TestCase
{
    private const PROBE = '/api/v1/notifications';

    public function test_an_unlisted_origin_receives_no_permission_to_read_the_api(): void
    {
        $preflight = $this->options(self::PROBE, [
            'Origin' => 'https://not-our-console.example',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'content-type',
        ]);

        // The status is not the security signal here: Laravel answers a non-granted
        // preflight with an empty 200 carrying `Allow` and `Vary` (the router's
        // OPTIONS handling), and the browser blocks the read because
        // `Access-Control-Allow-Origin` is absent. Pinned to 200 so that a future
        // framework change to 204/405 shows up as a diff rather than a silent
        // relaxation of what the header assertions below tolerate.
        $this->assertSame(200, $preflight->getStatusCode());
        $preflight->assertHeaderMissing('Access-Control-Allow-Origin');
        $preflight->assertHeader('Vary', 'Access-Control-Request-Method, Origin');

        $actual = $this->withHeader('Origin', 'https://not-our-console.example')->getJson(self::PROBE);
        $actual->assertHeaderMissing('Access-Control-Allow-Origin');
        $actual->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_the_shipped_config_names_no_origin_at_all(): void
    {
        $this->assertSame([], config('cors.allowed_origins'), 'the shipped config must not pre-authorize anyone');
        $this->assertSame([], config('cors.allowed_origins_patterns'), 'pattern allowlists match by regex and are wider than they look');
        $this->assertSame(['api/*'], config('cors.paths'));
        $this->assertFalse((bool) config('cors.supports_credentials'));
    }

    public function test_the_middleware_actually_reads_the_config(): void
    {
        config([
            'cors.allowed_origins' => ['https://console.example'],
        ]);

        $response = $this->withHeader('Origin', 'https://console.example')->getJson(self::PROBE);

        // If this ever stops holding, the "no header for everyone else" tests above
        // are measuring a config nobody consults, and the posture they appear to
        // prove is fiction.
        $response->assertHeader('Access-Control-Allow-Origin', 'https://console.example');
        $response->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_a_credentialed_config_is_the_one_change_that_would_let_a_foreign_page_read_the_console(): void
    {
        // The shipped file says `supports_credentials => false`, and the test above
        // proves it. What this records is the consequence of flipping it: with a
        // named origin plus credentials, that origin's JavaScript can read
        // authenticated API responses, because every route here is reachable with the
        // web session cookie. The header appearing below is the thing the config must
        // keep from happening. (No `Access-Control-Max-Age` is asserted: the
        // framework only emits it when it builds the preflight response itself, and
        // here the router's OPTIONS handling answers first — which is also why the
        // shipped `max_age` of 0 has no way to be wrong in the same breath.)
        config([
            'cors.allowed_origins' => ['https://console.example'],
            'cors.supports_credentials' => true,
            'cors.max_age' => 3600,
        ]);

        $preflight = $this->withHeader('Origin', 'https://console.example')->options(self::PROBE, [
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-idempotency-key',
        ]);

        $preflight->assertHeader('Access-Control-Allow-Credentials', 'true');

        // The shipped file, not the config this test just overrode.
        $shipped = require base_path('config/cors.php');
        $this->assertFalse($shipped['supports_credentials']);
        $this->assertSame(['Content-Type', 'X-Requested-With', 'X-Idempotency-Key', 'X-XSRF-TOKEN'], $shipped['allowed_headers']);
        $this->assertSame(0, $shipped['max_age'], 'a long preflight cache would keep a mistake in force for an hour after the fix ships');
    }

    public function test_a_wildcard_in_the_environment_is_not_an_allowlist(): void
    {
        // Re-read through the env repository, because that is the layer a deployment
        // actually sets: the config file itself is a function of the environment, and
        // a value the file ignores is the whole point.
        try {
            Env::getRepository()->set('CORS_ALLOWED_ORIGINS', '*');
            $config = require base_path('config/cors.php');
            $this->assertSame([], $config['allowed_origins'], 'CORS_ALLOWED_ORIGINS=* is how a credentialed cross-origin read starts');

            Env::getRepository()->set('CORS_ALLOWED_ORIGINS', 'https://a.example, ,https://b.example');
            $config = require base_path('config/cors.php');
            $this->assertSame(['https://a.example', 'https://b.example'], $config['allowed_origins']);
        } finally {
            Env::getRepository()->clear('CORS_ALLOWED_ORIGINS');
        }
    }

    public function test_paths_outside_the_api_cannot_be_granted_access_to_by_accident(): void
    {
        // The login form is the most valuable same-origin endpoint to a foreign page,
        // and it is not in `cors.paths`, so even a named origin gets nothing here.
        config(['cors.allowed_origins' => ['https://console.example']]);

        Route::middleware('api')->get('/probe-outside-cors-paths', static fn () => response('ok'));

        $this->withHeader('Origin', 'https://console.example')
            ->get('/probe-outside-cors-paths')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
