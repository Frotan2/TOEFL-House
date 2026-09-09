<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class IntegrationRetrySweepJobContractTest extends TestCase
{
    public function test_retry_sweep_is_bounded_and_requires_an_authenticated_operator(): void
    {
        $source = File::get(base_path('app/Modules/Integrations/Jobs/IntegrationRetrySweepJob.php'));

        $this->assertStringContainsString('private const DEFAULT_BATCH = 100;', $source);
        $this->assertStringContainsString('private const MAX_BATCH = 500;', $source);
        $this->assertStringContainsString("throw BusinessRejection::forCode('integrations.retry_operator_required'", $source);
        $this->assertStringContainsString('$batch = (int) ($context[\'batch\'] ?? self::DEFAULT_BATCH);', $source);
        $this->assertStringContainsString('$batch = max(1, min(self::MAX_BATCH, $batch));', $source);
        $this->assertStringContainsString('->limit($batch)', $source);
        $this->assertStringContainsString("new Actor($runBy, 'Integration Sweep')", $source);
    }
}
