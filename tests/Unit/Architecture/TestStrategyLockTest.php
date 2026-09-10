<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Locks the testing strategy defined in docs/TESTING_STRATEGY_LOCK.md.
 *
 * The repository previously used DatabaseMigrations, which replays the full
 * migration chain before every single test. That made the suite effectively
 * unrunnable (~21x slower on a measured sample) and hid real defects because a
 * full result was never obtainable.
 *
 * This test fails if that strategy is reintroduced, so the decision survives
 * as an enforced repository default rather than tribal knowledge.
 *
 * It deliberately extends PHPUnit's TestCase directly: it inspects the base
 * test case as a file and must not depend on the framework bootstrap it is
 * asserting about.
 */
final class TestStrategyLockTest extends PHPUnitTestCase
{
    private const BASE_TEST_CASE = __DIR__.'/../../TestCase.php';

    public function test_base_test_case_uses_refresh_database(): void
    {
        $source = (string) file_get_contents(self::BASE_TEST_CASE);

        $this->assertStringContainsString(
            'use RefreshDatabase;',
            $source,
            'tests/TestCase.php must use RefreshDatabase. See docs/TESTING_STRATEGY_LOCK.md.'
        );
    }

    public function test_base_test_case_does_not_use_database_migrations(): void
    {
        $source = $this->withoutComments((string) file_get_contents(self::BASE_TEST_CASE));

        $this->assertStringNotContainsString(
            'DatabaseMigrations',
            $source,
            'DatabaseMigrations replays the full migration chain per test and must not be '
            .'reintroduced in the base TestCase. See docs/TESTING_STRATEGY_LOCK.md §1.'
        );
    }

    public function test_no_test_reintroduces_database_migrations(): void
    {
        $offenders = [];

        foreach ($this->collectTestFiles() as $file) {
            $source = $this->withoutComments((string) file_get_contents($file));
            if (str_contains($source, 'Illuminate\\Foundation\\Testing\\DatabaseMigrations')) {
                $offenders[] = $this->relative($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "DatabaseMigrations must not be used. Offending files:\n  ".implode("\n  ", $offenders)
            ."\nSee docs/TESTING_STRATEGY_LOCK.md §1 and §3 for the sanctioned exceptions."
        );
    }

    public function test_there_is_exactly_one_base_test_case(): void
    {
        $bases = [];

        foreach ($this->collectTestFiles() as $file) {
            $source = (string) file_get_contents($file);
            // A second class extending the framework TestCase directly would be
            // a parallel base able to choose its own isolation strategy.
            if (preg_match('/class\s+\w+\s+extends\s+\\\\?Illuminate\\\\Foundation\\\\Testing\\\\TestCase/', $source) === 1) {
                $bases[] = $this->relative($file);
            }
        }

        $this->assertSame(
            ['tests/TestCase.php'],
            $bases,
            'Exactly one base test case may extend the framework TestCase, so the '
            .'isolation strategy has a single owner. Found: '.implode(', ', $bases)
        );
    }

    /**
     * Strips comments so documentation that *names* the forbidden strategy in
     * order to explain the rule is not mistaken for using it.
     */
    private function withoutComments(string $source): string
    {
        $out = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    /** @return list<string> */
    private function collectTestFiles(): array
    {
        $root = realpath(__DIR__.'/../../') ?: __DIR__.'/../../';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        $root = realpath(__DIR__.'/../../../') ?: '';

        return 'tests/'.ltrim(str_replace($root.'/tests', '', $path), '/');
    }
}
