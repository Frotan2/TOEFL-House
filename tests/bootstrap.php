<?php

declare(strict_types=1);

/*
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader, then the sandbox database bootstrap (a no-op
 * wherever the native pdo_pgsql driver is available).
 */

require __DIR__.'/../vendor/autoload.php';

// Defensive base path for environments where the composer loader registry is
// unavailable (Application::inferBasePath falls back to it). Identical to the
// path Composer inference would resolve.
$_ENV['APP_BASE_PATH'] ??= dirname(__DIR__);
$_SERVER['APP_BASE_PATH'] ??= dirname(__DIR__);

/*
 * Make phpunit.xml authoritative for the settings it pins.
 *
 * PHPUnit applies <env name="X" value="Y" force="true"/> with putenv() and
 * $_ENV only. PHP also copies the process environment into $_SERVER
 * (variables_order=EGPCS), and Laravel's env() consults $_SERVER before $_ENV —
 * so a variable inherited from the shell still wins and `force="true"` is
 * decorative for the one setting where it matters most. This is not academic:
 * an exported DB_DATABASE=toefl_house_prod (easy to acquire from a deployment
 * shell) redirects RefreshDatabase, whose first step is `migrate:fresh`, at a
 * real production database and drops every table in it.
 *
 * Re-asserting the forced entries into $_SERVER closes the precedence hole
 * regardless of what the invoking shell had set.
 */
(static function (): void {
    $config = __DIR__.'/../phpunit.xml';

    if (! is_file($config)) {
        return;
    }

    $xml = @simplexml_load_file($config);

    if ($xml === false || ! isset($xml->php->env)) {
        return;
    }

    foreach ($xml->php->env as $env) {
        $attributes = $env->attributes();

        if ($attributes === null || (string) ($attributes['force'] ?? '') !== 'true') {
            continue;
        }

        $name = (string) ($attributes['name'] ?? '');
        // The value lives in the `value` attribute, not in element text — reading
        // (string) $env yields '' and silently blanks the setting it pins.
        $value = (string) ($attributes['value'] ?? '');

        if ($name === '' || $value === '') {
            continue;
        }

        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }
})();

if (! extension_loaded('pdo_pgsql')) {
    require_once __DIR__.'/Support/PgWire/bootstrap.php';
}
