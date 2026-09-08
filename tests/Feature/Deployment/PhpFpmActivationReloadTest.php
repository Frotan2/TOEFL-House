<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Activating a release and making the runtime execute it are two different acts,
 * and only the second one can be verified from the shell.
 *
 * With `opcache.validate_timestamps=0` — the documented production setting, and the
 * reason `config:cache`/`route:cache`/`view:cache` are worth building at all — a
 * running PHP-FPM master never stats a PHP file again. Moving the `current` symlink
 * therefore changes nothing for the live pool: it keeps executing the release it
 * loaded first. `deploy.sh` used to reload it like this:
 *
 *   ( systemctl reload php*-fpm 2>/dev/null || service php*-fpm reload 2>/dev/null || true )
 *
 * On a host with neither service manager (containers, supervisor-managed pools, this
 * rehearsal cluster) that expression succeeds *by doing nothing*, the script then
 * polled /health, got 200 from the old release, and printed "deployment OK".
 * Measured on 2026-09-08: `current` pointed at `releases/20260908191650` while a
 * live request was served from `releases/20260908191449/public/index.php` — new
 * schema, old application, and no failure signal anywhere. `--rollback` could not
 * have helped either, since it also only moves the symlink.
 *
 * These assertions pin the replacement: exactly one reload mechanism must succeed,
 * the pool's reload command and pid file are host facts that can be overridden, and
 * when no mechanism works the deployment restores the previous symlink and exits
 * non-zero instead of reporting success. Verified executably in
 * docs/AUDIT-2026-09-08-GATE-EVIDENCE.md (Gate D): with no override the rehearsal now
 * stops at this step, and with `PHP_FPM_RELOAD_CMD` set the post-deploy failure log
 * is written by the newly activated release rather than the previous one.
 */
final class PhpFpmActivationReloadTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('deploy/deploy.sh'));
    }

    public function test_the_reload_is_attempted_through_a_documented_fallback_chain(): void
    {
        $script = $this->script();

        foreach (['$PHP_FPM_RELOAD_CMD', 'systemctl reload', 'service php*-fpm reload', 'kill -USR2'] as $mechanism) {
            $this->assertStringContainsString(
                $mechanism,
                $script,
                "the reload must try {$mechanism}: an operator on a systemd host, a sysvinit host, a supervisor-managed pool "
                .'and a bare container need four different things, and each must be reachable without editing the script.'
            );
        }
    }

    public function test_the_reload_is_not_allowed_to_fail_silently(): void
    {
        $script = $this->script();

        // The exact shape of the old bug: a reload whose failure is swallowed by `||
        // true` inside a subshell. `bash -n` cannot see it and no PHP test could,
        // because the symptom is which *release serves traffic*, not an error string.
        $this->assertMatchesRegularExpression(
            '/reload_php_fpm\(\) \{/',
            $script,
            'the reload must be a function whose status the script inspects, not an inline expression'
        );

        $this->assertStringContainsString(
            'if ! reload_php_fpm; then',
            $script,
            'a failed reload must be a checked condition'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/systemctl reload php\*-fpm 2>\/dev\/null \|\| service php\*-fpm reload 2>\/dev\/null \|\| true/',
            $script,
            'the reload chain must not end in `|| true`: on a host with no service manager that succeeds while serving the previous release'
        );
    }

    public function test_a_failed_reload_leaves_the_symlink_matching_what_is_executing(): void
    {
        $script = $this->script();

        $start = strpos($script, 'if ! reload_php_fpm; then');
        $this->assertNotFalse($start, 'the reload result must be checked before anything else happens');

        $branch = substr($script, $start, 1200);

        $this->assertStringContainsString('ln -sfn "$PREV_RELEASE" "$CURRENT_LINK"', $branch);
        $this->assertStringContainsString('die "', $branch, 'the deployment must fail, not report success');
        $this->assertStringContainsString('PHP_FPM_RELOAD_CMD', $branch, 'the refusal must name the override that fixes it');
        // The database has already been migrated at this point; saying so is the
        // difference between an operator checking and an operator assuming.
        $this->assertMatchesRegularExpression('/database was already migrated/i', $branch);
    }

    public function test_the_reload_inputs_are_overridable_host_facts(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('PHP_FPM_RELOAD_CMD="${PHP_FPM_RELOAD_CMD:-}"', $script);
        $this->assertStringContainsString('PHP_FPM_PID_FILE="${PHP_FPM_PID_FILE:-}"', $script);
    }
}
