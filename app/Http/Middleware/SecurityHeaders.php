<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response. Defense in depth: the same
 * headers are also set at the web server (see deploy/nginx) so they are
 * present even for statically-served assets. These headers constrain how
 * browsers treat the console; they do not substitute for transport security
 * (HTTPS) or authorization, which are enforced elsewhere.
 */
final class SecurityHeaders
{
    /**
     * The Content-Security-Policy served in production, and the policy
     * `deploy/nginx/toefl-house.conf` must match byte for byte.
     *
     * It was written from measurement rather than from a template, because the
     * usual template ("'unsafe-inline' everywhere so nothing breaks") is the one
     * that makes a CSP decorative for script execution. Inventorying the deployed
     * release — the login page and all 14 authenticated consoles in a real browser
     * — produced: zero inline <script> elements (0 bytes), zero inline event
     * handlers, zero cross-origin scripts, zero iframes / <object> / <embed>, zero
     * blob:- or data:-typed scripts, and every fetch going to a same-origin
     * relative path. The shipped bundles (app-*.js and the 11 module chunks)
     * contain no `eval(`, no `new Function(`, no `.innerHTML =`, no
     * `document.write`, no `Worker`, no `URL.createObjectURL`, no canvas
     * `toDataURL`, and the built CSS contains no `url()` and no `@font-face`
     * fetch. `script-src 'self'` therefore costs nothing today, which is what
     * makes it enforceable rather than report-only.
     *
     * Styles keep `'unsafe-inline'`, deliberately and only there: Vite injects a
     * <style> element at runtime and Blade-authored markup carries a handful of
     * style="" attributes (measured: 1 style block and 6 attributes per page).
     * Inline CSS cannot execute script; relaxing script-src would.
     *
     * Deliberately absent, each for a stated reason:
     *   * no nonce/hash — there is nothing inline to allow, so a nonce would be
     *     per-request cost for zero benefit and a reason for the policy to rot;
     *   * no `upgrade-insecure-requests` — it rewrites same-origin http: URLs, so
     *     it breaks `php artisan serve` development runs while protecting nothing
     *     here (no cross-protocol subresource exists to upgrade);
     *   * no `report-to`/`report-uri` — the repository has no collector to receive
     *     violations, and pointing at a nonexistent endpoint is noise. This is a
     *     real gap (a violated directive is invisible to us, so any future inline
     *     script simply fails silently in the browser) and is recorded as such in
     *     docs/AUDIT-2026-09-08-GATE-EVIDENCE.md §Gate E.
     */
    public const CSP_PRODUCTION = "default-src 'self'; base-uri 'self'; script-src 'self'; "
        ."style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; connect-src 'self'; "
        ."form-action 'self'; frame-ancestors 'none'; frame-src 'none'; object-src 'none'; "
        ."worker-src 'none'; manifest-src 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        // Strict-Transport-Security is meaningful only over HTTPS; the web
        // server is the authoritative place for it (it knows the scheme). We
        // still emit it so a misconfigured direct-to-PHP path is not assumed
        // to be downgradable by a client.
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Content-Security-Policy', self::policy());

        return $response;
    }

    /**
     * The production policy, except that a local Vite dev server is allowed to
     * load and hot-reload the front end.
     *
     * `@vite` points the browser at a different origin when the dev server is
     * running, so a strict `script-src 'self'` would take the entire UI down in
     * local development — and the predictable response to that is for someone to
     * delete or weaken the header everywhere. The allowance is granted only when
     * *all* of these hold: the environment is not production, the `public/hot`
     * marker exists (Laravel's own signal that the dev server is in use), and the
     * marker names a parseable http(s) origin. Production output is therefore
     * byte-identical to the web server's, which is what the parity test pins.
     */
    public static function policy(): string
    {
        $dev = self::devServerOrigin();

        if ($dev === null) {
            return self::CSP_PRODUCTION;
        }

        $ws = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $dev);

        return str_replace(
            ["script-src 'self';", "connect-src 'self';"],
            ["script-src 'self' {$dev};", "connect-src 'self' {$dev} {$ws};"],
            self::CSP_PRODUCTION
        );
    }

    private static function devServerOrigin(): ?string
    {
        if (app()->environment('production')) {
            return null;
        }

        $marker = public_path('hot');

        if (! is_file($marker)) {
            return null;
        }

        $declared = trim((string) @file_get_contents($marker));

        // Vite writes the URL only when it differs from its default.
        if ($declared === '') {
            $declared = 'http://localhost:5173';
        }

        $parts = parse_url($declared);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
