<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Shell script delivery contract: what is executed must arrive executable, and
 * what is sourced must say so.
 *
 * This is not cosmetic. deploy.sh calls `"$RELEASE_DIR/deploy/backup.sh"`, and
 * docs/operations/production-deployment.md tells the operator to run
 * `./deploy/deploy.sh <git-ref>`. While deploy.sh, backup.sh and restore.sh were
 * committed 100644, the Gate A rehearsal died mid-deployment — after the
 * composer install, after `npm ci` and the Vite build, and immediately before
 * the pre-deploy backup that the script treats as a hard precondition for
 * running forward-only migrations — with "Permission denied" (exit 126). The
 * documented entry point did not work from a clone at all, and the existing
 * deployment test could not see it because it only checked that the paths exist.
 *
 * Conversely deploy/lib/retention.sh is `source`d, so it must not be executable:
 * an executable script with no shebang is run by /bin/sh, where `set -o
 * pipefail`, arrays and process substitution do not exist.
 */
final class ShippedScriptPermissionsTest extends TestCase
{
    /** Scripts that are executed by path (by deploy.sh, or by the operator). */
    private const EXECUTED_BY_PATH = [
        'deploy/deploy.sh',
        'deploy/backup.sh',
        'deploy/restore.sh',
        'deploy/schema-compatibility.sh',
        'deploy/schedule.sh',
        'deploy/render-nginx-config.sh',
        'deploy/certbot/reload-nginx.sh',
    ];

    public function test_scripts_that_are_executed_by_path_are_committed_executable(): void
    {
        $modes = $this->trackedModes();

        foreach (self::EXECUTED_BY_PATH as $path) {
            $this->assertArrayHasKey($path, $modes, "{$path} must be tracked: the deployment docs and deploy.sh invoke it");
            $this->assertContains(
                $modes[$path],
                ['100755', '100777'],
                "{$path} is executed by path, so it must be committed with the exec bit (git update-index --chmod=+x {$path}). ".
                'The mode that matters is the one in the index: a fresh clone is what a deployment gets.'
            );
            $this->assertTrue(is_executable(base_path($path)), "{$path} is not executable in this checkout either");
        }
    }

    public function test_every_executable_shell_script_declares_the_bash_interpreter(): void
    {
        $checked = 0;

        foreach ($this->trackedModes() as $path => $mode) {
            if (! in_array($mode, ['100755', '100777'], true)) {
                continue;
            }

            $first = trim((string) (file(base_path($path), FILE_IGNORE_NEW_LINES) ?: [''])[0]);
            $this->assertSame('#!/usr/bin/env bash', $first, "{$path} is executable; without a bash env-shebang the kernel picks /bin/sh");
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'the repository ships executable shell scripts, so this guard must actually run');
    }

    public function test_every_non_executable_shell_script_documents_itself_as_sourced(): void
    {
        foreach ($this->trackedModes() as $path => $mode) {
            if (in_array($mode, ['100755', '100777'], true)) {
                continue;
            }

            $lines = array_slice(file(base_path($path), FILE_IGNORE_NEW_LINES) ?: [], 0, 20);
            $head = implode("\n", $lines);

            $this->assertMatchesRegularExpression(
                '/source[sd]?\b|\bsourced\b/i',
                $head,
                "{$path} is a non-executable shell file: its header must state that it is sourced, and how"
            );
        }
    }

    public function test_no_deploy_script_is_executed_by_a_path_that_could_escape_the_release(): void
    {
        // deploy.sh must reach its helpers inside the release it just built, not
        // through an absolute or ambient path that would survive a moved
        // DEPLOY_ROOT (and would let a stale install shadow the new release).
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));

        $this->assertStringContainsString('"$RELEASE_DIR/deploy/backup.sh"', $deploy, 'the backup must come from the release being activated');
        $this->assertStringContainsString('"$SCHEMA_PROBE"', $deploy);
        $this->assertStringContainsString('SCHEMA_PROBE="${SCHEMA_PROBE:-$SCRIPT_DIR/schema-compatibility.sh}"', $deploy, 'the probe must default to the copy next to deploy.sh');
    }

    /**
     * `git ls-files -s` for tracked shell scripts, as path => mode.
     *
     * @return array<string, string>
     */
    private function trackedModes(): array
    {
        $output = [];
        exec('git ls-files -s -- "*.sh" 2>/dev/null', $output);

        $modes = [];
        foreach ($output as $line) {
            // "<mode> <sha> <stage>\t<path>"
            [$meta, $path] = array_pad(explode("\t", $line, 2), 2, '');
            if (trim($path) === '') {
                continue;
            }

            $modes[trim($path)] = trim(explode(' ', $meta)[0]);
        }

        return $modes;
    }
}
