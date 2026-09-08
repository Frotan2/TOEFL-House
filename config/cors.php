<?php

declare(strict_types=1);

/*
 * Cross-origin posture, declared rather than inherited.
 *
 * The application is same-origin by construction: the Blade console and the JSON
 * API are served from one host, and the console calls `/api/v1/...` with cookies.
 * Nothing in the product needs another origin to read it with credentials.
 *
 * `bootstrap/app.php` installs `HandleCors` globally. Without this file the
 * middleware finds no `cors.paths` and therefore adds no header at all, so a
 * browser blocks every cross-origin read. That is the behaviour we want, but it
 * was a side effect of a missing file: publishing a partial config later — or
 * copying `allowed_origins => ['*']` out of a package README while
 * `supports_credentials` is true — would open credentialed cross-origin reads
 * with no test noticing. So the paths the middleware inspects are listed here,
 * the allowlist is empty by default, and the only way to widen it is the
 * `CORS_ALLOWED_ORIGINS` variable, which is a comma-separated list of exact
 * origins.
 *
 * Two rules the deployment must not quietly break, both asserted in
 * tests/Feature/Security/ApiCorsPostureTest.php:
 *   - no `Access-Control-Allow-Origin` for an origin that was not named;
 *   - `supports_credentials` stays false, because every API route is reachable
 *     with the web session cookie and a granted origin plus credentials is the
 *     combination that turns a third-party page into the console.
 */

return [

    /*
     * The origins that may read responses from their own JavaScript. Empty means
     * no origin at all, and the wildcard is deliberately not supported here: an
     * operator who types `*` gets an empty list rather than a wide-open API.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ), static fn (string $origin): bool => $origin !== '' && $origin !== '*')),

    'allowed_origins_patterns' => [],

    /*
     * Same-origin clients send cookies whether or not we ask; the API answers for
     * a session principal, so there is no configuration in which allowing
     * credentials from another origin is correct.
     */
    'supports_credentials' => false,

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-Idempotency-Key', 'X-XSRF-TOKEN'],

    /*
     * Listed rather than `*` because these are the verbs `routes/api.php` defines,
     * and a preflight cache entry for a method the API does not serve is a header
     * the deployment does not need to hand out.
     */
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * Listed so the middleware is actually consulted for the API surface. Paths
     * outside this list cannot receive a CORS header even by mistake.
     */
    'paths' => ['api/*'],

];
