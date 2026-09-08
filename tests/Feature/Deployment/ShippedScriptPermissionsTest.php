<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Shell scripts the operator (or deploy.sh itself) invokes by path must arrive
 * executable from a fresh clone.
 *
 * This is not cosmetic: deploy.sh calls `"$RELEASE_DIR/deploy/backup.sh"`, and
 * docs/operations/production-deployment.md tells the operator to run
 * `./deploy/deploy.sh <git-ref>`. While deploy.sh, backup.sh and restore.sh were
 * committed as 100644, the pre-deploy backup step aborted with
 * "Permission denied" - i.e. mid-deployment, after the build, before any
 * migration - and the documented entry point did not work at all from a clone.
 * The mode lives in the git index, so the index is what this test reads; a
 * working-tree chmod that was never committed is exactly the bug.
 */
final class ShippedScriptPermissionsTest extends TestCase
{
    public function test_every_tracked_shell_script_is_committed_executable(): void
    {
        $notExecutable = [];

        foreach ($this->trackedModes('*.sh') as $path => $mode) {
            if (! in_array($mode, ['100755', '100777'], true)) {
                $notExecutable[] = "{$path} (mode {$mode})";
            }
        }

        $this->assertSame(
            [],
            $notExecutable,
            'tracked shell scripts invoked by path must be committed with the exec bit (git update-index --chmod=+x <file>)'
        );
        $this->assertNotEmpty($this->trackedModes('*.sh'), 'the guard is only meaningful if the repository ships shell scripts');
    }

    public function test_every_shell_script_either_starts_with_a_shebang_or_is_a_sourced_library(): void
    {
        // Executable + no shebang means the kernel runs it with /bin/sh, where
        // `set -o pipefail`, arrays and process substitution do not exist. A
        // script that is only ever `source`d (scripts/runtime/env.sh) needs no
        // shebang, but it has to say so, or a later reader will exec it.
        foreach (array_keys($this->trackedModes('*.sh')) as $path) {
            $lines = file(base_path($path), FILE_IGNORE_NEW_LINES) ?: [];
            $first = trim($lines[0] ?? '');

            if (str_starts_with($first, '#!')) {
                $this->assertSame(
                    '#!/usr/bin/env bash',
                    $first,
                    "{$path} is executed by path, so its interpreter line must be the bash env form used across the repo"
                );

                continue;
            }

            $head = implode("\n", array_slice($lines, 0, 15));
            $this->assertStringContainsString(
                'source '.$path,
                $head,
                "{$path} has no shebang and is executable: document it as a sourced library, or give it a bash shebang"
            );
        }
    }

    public function test_the_documented_deployment_entry_points_exist_and_are_executable(): void
    {
        foreach (['deploy/deploy.sh', 'deploy/backup.sh', 'deploy/restore.sh', 'deploy/schema-compatibility.sh'] as $path) {
            $modes = $this->trackedModes($path);

            $this->assertArrayHasKey($path, $modes, "{$path} must be tracked: it is referenced by the deployment docs");
            $this->assertContains($modes[$path], ['100755', '100777'], "{$path} must be committed executable");
            $this->assertTrue(is_executable(base_path($path)), "{$path} is not executable on disk");
        }
    }

    /**
     * `git ls-files -s` for a pathspec, as path => mode.
     *
     * @return array<string, string>
     */
    private function trackedModes(string $pathspec): array
    {
        $output = [];
        exec('git ls-files -s -- '.escapeshellarg($pathspec).' 2>/dev/null', $output);

        $modes = [];
        foreach ($output as $line) {
            // "<mode> <sha> <stage>\t<path>"
            [$meta, $path] = array_pad(explode("\t", $line, 2), 2, '');
            if ($path === '') {
                continue;
            }
            $modes[trim($path)] = trim(explode(' ', $meta)[0]);
        }

        return $modes;
    }
}
