<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * A deployment without REPO_URL must fetch this repository, not the historical
 * fork it originated from. The old default remained cloneable, so the failure
 * mode was especially dangerous: a host would report a successful deployment
 * while silently building a stale and differently governed source tree.
 */
final class DeploymentSourceContractTest extends TestCase
{
    private const CANONICAL_REPOSITORY = 'https://github.com/Frotan2/TOEFL-House.git';

    public function test_the_default_release_source_is_the_canonical_repository(): void
    {
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));

        $this->assertStringContainsString(
            'REPO_URL="${REPO_URL:-'.self::CANONICAL_REPOSITORY.'}"',
            $deploy,
            'a deployment without REPO_URL must clone this repository rather than a stale historical fork',
        );
        $this->assertStringNotContainsString(
            'alfrotan-glitch/TOEFL-House',
            $deploy,
            'the historical repository remains cloneable, so it must never be the silent deployment default',
        );
    }

    public function test_the_production_runbook_documents_the_default_and_reviewed_override(): void
    {
        $runbook = (string) file_get_contents(base_path('docs/operations/production-deployment.md'));

        $this->assertStringContainsString(self::CANONICAL_REPOSITORY, $runbook);
        $this->assertStringContainsString('set `REPO_URL` only', $runbook);
    }
}
