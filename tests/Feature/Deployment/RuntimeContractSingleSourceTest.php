<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The runtime contract must be stated once.
 *
 * deploy.sh used to carry its own copy of the supported versions as exact
 * patch pins (PHP 8.2.27, Node 22.23.1, npm 10.9.2, PostgreSQL 18.4) while the
 * authoritative lock (docs/RUNTIME_ENVIRONMENT_LOCK.md) had moved to ranges and
 * to an executed PHP 8.4.14. The two copies then disagreed, and the deploy
 * script refused every release it was pointed at. These tests keep exactly one
 * place where a version is declared, and execute the enforcer that deploy.sh
 * delegates to.
 */
final class RuntimeContractSingleSourceTest extends TestCase
{
    private const LOCK_DOC = 'docs/RUNTIME_ENVIRONMENT_LOCK.md';

    public function test_the_deploy_script_holds_no_runtime_version_numbers_of_its_own(): void
    {
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));

        foreach (['EXPECTED_PHP_VERSION', 'EXPECTED_NODE_VERSION', 'EXPECTED_NPM_VERSION', 'EXPECTED_POSTGRES_VERSION'] as $gone) {
            $this->assertStringNotContainsString($gone.'="', $deploy, "deploy.sh must not re-declare {$gone}");
        }

        $this->assertStringContainsString(
            'scripts/runtime/verify-environment.mjs',
            $deploy,
            'deploy.sh must verify the runtime through the environment lock, not through its own comparison'
        );
    }

    public function test_package_json_engines_are_ranges_and_match_the_lock_document(): void
    {
        $engines = (array) (json_decode((string) file_get_contents(base_path('package.json')), true)['engines'] ?? []);
        $lock = (string) file_get_contents(base_path(self::LOCK_DOC));

        $this->assertSame(['node', 'npm'], array_keys($engines), 'package.json declares the Node/npm contract and nothing else');

        foreach ($engines as $name => $range) {
            $this->assertStringStartsWith('>=', $range, "engines.{$name} must be a range: a patch pin is what drifted last time");
            $this->assertStringContainsString(
                $range,
                $lock,
                "engines.{$name} ({$range}) contradicts the allowed range recorded in ".self::LOCK_DOC
            );
        }
    }

    public function test_the_deployment_documentation_states_the_same_ranges_as_the_lock(): void
    {
        $doc = (string) file_get_contents(base_path('docs/operations/production-deployment.md'));
        $lock = (string) file_get_contents(base_path(self::LOCK_DOC));

        foreach (['PHP' => '>=8.2 <8.5', 'PostgreSQL' => '>=18.0 <19.0', 'Node.js' => '>=22.0 <23.0', 'npm' => '>=10.0'] as $component => $range) {
            $this->assertStringContainsString($range, $doc, "production-deployment.md must require {$component} {$range}");
            $this->assertStringContainsString($range, $lock, "{$component} range is not what the lock declares");
        }

        // The document must state ranges, not require an exact patch release.
        $this->assertStringNotContainsString('**8.2.27**', $doc, 'the requirements table must not carry a patch pin');
        $this->assertStringNotContainsString('requires PHP 8.2.27', $doc, 'the prose must not require an exact patch release');
    }

    public function test_the_host_paths_the_deploy_script_guesses_are_overridable_once(): void
    {
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));

        // The FPM pool location and the web user are host facts, not repo facts:
        // each must have exactly one default and one override, and the ownership
        // step must fail with the remedy in the message instead of a raw chown
        // error (which is how the Gate A rehearsal died after a clean migration).
        $this->assertSame(
            1,
            substr_count($deploy, '/etc/php/*/fpm/pool.d/toefl-house.conf'),
            'the pool location must be spelled once, not re-derived per step'
        );
        $this->assertStringContainsString('WEB_USER="${WEB_USER:-', $deploy, 'the web user must be overridable like every other host input');
        $this->assertStringContainsString('set WEB_USER to the user PHP-FPM serves as', $deploy, 'a refused chown must name the remedy');
    }

    public function test_the_enforcer_deploy_script_delegates_to_actually_passes_here(): void
    {
        // Not a text assertion: run the same command deploy.sh step 2b runs, in
        // this checkout. If the host cannot satisfy the lock, deployment cannot
        // proceed, and this suite must say so before a release attempt does.
        $command = 'node '.escapeshellarg(base_path('scripts/runtime/verify-environment.mjs')).' 2>&1';
        $output = [];
        $exit = 0;
        exec($command, $output, $exit);

        $this->assertSame(
            0,
            $exit,
            "npm run verify:environment failed on this host:\n".implode("\n", $output)
        );
        $this->assertStringContainsString('ENVIRONMENT LOCK:', implode("\n", $output));
    }
}
