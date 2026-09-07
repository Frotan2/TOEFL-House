<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\Program;
use App\Support\Authorization\Actor;

/**
 * Canonical Academic setup chain.
 *
 * A class cannot be defined from a program version and period alone. The
 * domain requires the full chain, and deliberately refuses to infer any of it:
 *
 *     program -> published version -> level
 *             -> published period
 *             -> branch availability -> open offering -> class
 *
 * Tests that skipped the level/availability/offering steps were rejected with
 * "a new class must reference an open offering for its branch, level, and
 * period", which is the domain behaving correctly. This trait exists so that
 * prerequisite is expressed once instead of being re-derived per test.
 */
trait BuildsAcademicStructure
{
    /**
     * Builds the full chain and returns its identifiers.
     *
     * @return array{
     *     branch_id: string,
     *     program_id: string,
     *     program_version_id: string,
     *     level_id: string,
     *     period_id: string,
     *     offering_id: string
     * }
     */
    private function buildAcademicChain(
        Actor $officer,
        string $keyPrefix,
        int $offeringCapacity = 25,
        ?string $branchId = null,
    ): array {
        $structure = app(MaintainAcademicStructure::class);
        $branchId ??= $this->bootstrapBranchId();

        $program = $structure->defineProgram($officer, 'Program '.$keyPrefix, $keyPrefix.'-prog');
        $version = $structure->publishVersion(
            $officer,
            Program::query()->findOrFail($program['program_id']),
            'rules '.$keyPrefix,
            $keyPrefix.'-ver',
        );
        $level = $structure->defineLevel(
            $officer,
            $version['version_id'],
            'level-'.$keyPrefix,
            1,
            'Level '.$keyPrefix,
            'A1',
            $keyPrefix.'-lvl',
        );

        $period = $structure->definePeriod(
            $officer,
            'Term '.$keyPrefix,
            new \Carbon\CarbonImmutable('2026-09-01'),
            new \Carbon\CarbonImmutable('2026-12-18'),
            $keyPrefix.'-period',
        );
        $structure->transitionPeriod(
            $officer,
            AcademicPeriod::query()->findOrFail($period['period_id']),
            'published',
            $keyPrefix.'-period-pub',
        );

        // An offering may only be opened where the level is declared available.
        $structure->declareBranchAvailability(
            $officer,
            $branchId,
            $level['level_id'],
            $period['period_id'],
            $keyPrefix.'-avail',
        );

        $offering = $structure->openOffering(
            $officer,
            $branchId,
            $level['level_id'],
            $period['period_id'],
            $offeringCapacity,
            $keyPrefix.'-offering',
        );

        return [
            'branch_id' => $branchId,
            'program_id' => $program['program_id'],
            'program_version_id' => $version['version_id'],
            'level_id' => $level['level_id'],
            'period_id' => $period['period_id'],
            'offering_id' => $offering['offering_id'],
        ];
    }
}
