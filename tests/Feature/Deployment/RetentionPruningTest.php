<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Retention pruning must never be able to fail an otherwise successful backup
 * or deployment.
 *
 * Both deploy/backup.sh and deploy/deploy.sh used an inline
 * `ls -1t <glob> | tail -n +N | xargs rm -f` pipeline. `ls` exits 2 when its glob
 * matches nothing (normal for a young backup directory with no .age files), and
 * under this repository's `set -euo pipefail` that status leaves the pipeline:
 * the Gate A rehearsal observed a verified pg_dump being reported as a failed
 * backup, and `grep -v` in the release-pruning line would have reported a
 * healthy deployment as failed after go-live. Both now share
 * deploy/lib/retention.sh, which is executed here.
 */
final class RetentionPruningTest extends TestCase
{
    private string $lib;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lib = base_path('deploy/lib/retention.sh');
        $this->assertTrue(is_executable($this->lib) || is_file($this->lib), 'the retention helper must ship with the deploy scripts');
    }

    public function test_pruning_a_directory_with_no_matching_files_is_not_an_error(): void
    {
        $dir = $this->tempDir();

        $run = $this->prune($dir, 'toefl_house_prod-*.dump', 14);

        $this->assertSame(0, $run['exit'], "nothing to prune must not fail: {$run['output']}");
        $this->assertSame([], $this->listing($dir));
    }

    public function test_pruning_a_missing_directory_is_not_an_error(): void
    {
        $run = $this->prune(sys_get_temp_dir().'/toefl-retention-'.uniqid('', true).'/gone', '*.dump', 14);

        $this->assertSame(0, $run['exit'], $run['output']);
    }

    public function test_only_the_newest_kept_entries_survive(): void
    {
        $dir = $this->tempDir();

        // Six dumps, newest last; keep 3.
        foreach (range(1, 6) as $n) {
            file_put_contents("{$dir}/db-{$n}.dump", 'x');
            touch("{$dir}/db-{$n}.dump", time() + $n);
        }

        $run = $this->prune($dir, 'db-*.dump', 3);

        $this->assertSame(0, $run['exit'], $run['output']);
        $this->assertSame(['db-4.dump', 'db-5.dump', 'db-6.dump'], $this->listing($dir));
    }

    public function test_release_directories_are_pruned_the_same_way(): void
    {
        $dir = $this->tempDir();

        // deploy.sh keeps the current release plus three previous ones.
        foreach (range(1, 6) as $n) {
            mkdir("{$dir}/2026090{$n}000000", 0777, true);
            touch("{$dir}/2026090{$n}000000", time() + $n);
        }

        $run = $this->prune($dir, '*', 4, 'rm -rf');

        $this->assertSame(0, $run['exit'], $run['output']);
        $this->assertCount(4, $this->listing($dir));
        $this->assertContains('20260906000000', $this->listing($dir), 'the newest release must never be pruned');
    }

    public function test_both_deploy_scripts_use_the_shared_helper(): void
    {
        $backup = (string) file_get_contents(base_path('deploy/backup.sh'));
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));

        foreach (['backup' => $backup, 'deploy' => $deploy] as $name => $script) {
            $this->assertStringContainsString('prune_old_entries', $script, "{$name}.sh must prune through the tested helper");
            $this->assertStringNotContainsString('ls -1t', $script, "{$name}.sh must not go back to a bare ls pipeline: its exit status is the bug");
            $this->assertStringContainsString('lib/retention.sh', $script, "{$name}.sh must load the helper it depends on");
        }
    }

    public function test_every_sourced_helper_path_in_the_deploy_scripts_resolves(): void
    {
        // The scripts locate deploy/lib/retention.sh relative to themselves, and
        // a wrong component there only fails on a real deployment (which is how
        // `deploy/deploy/lib/retention.sh` was briefly shipped).
        $matched = 0;

        foreach (glob(base_path('deploy/*.sh')) ?: [] as $script) {
            foreach (file($script, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
                if (! str_starts_with(trim($line), 'source ')) {
                    continue;
                }

                if (preg_match('#\$\{?(?:BACKUP_DIR_SELF|SCRIPT_DIR)\}?/([A-Za-z0-9_./-]+)#', $line, $match) !== 1) {
                    $this->fail(basename($script).':'.($number + 1).' sources a path this test cannot resolve');
                }

                $matched++;
                $this->assertFileExists(
                    dirname($script).'/'.$match[1],
                    basename($script).':'.($number + 1).' sources a path that does not exist next to the script'
                );
            }
        }

        $this->assertGreaterThan(0, $matched, 'the deploy scripts are expected to source at least one helper');
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function prune(string $dir, string $glob, int $keep, string $delete = 'rm -f'): array
    {
        // Exactly the shell options the real scripts run under.
        $script = sprintf(
            'set -euo pipefail; source %s; prune_old_entries %s %s %d %s',
            escapeshellarg($this->lib),
            escapeshellarg($dir),
            escapeshellarg($glob),
            $keep,
            escapeshellarg($delete)
        );

        $output = [];
        $exit = 0;
        exec('bash -c '.escapeshellarg($script).' 2>&1', $output, $exit);

        return ['exit' => (int) $exit, 'output' => implode("\n", $output)];
    }

    /**
     * @return array<int, string>
     */
    private function listing(string $dir): array
    {
        $entries = array_values(array_filter(scandir($dir) ?: [], static fn (string $e): bool => $e !== '.' && $e !== '..'));
        sort($entries);

        return $entries;
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir().'/toefl-retention-'.uniqid('', true);
        $this->assertNotFalse(mkdir($dir, 0777, true));

        return $dir;
    }
}
