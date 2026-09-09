<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Models\ProgramVersionLevel;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * WP-2 F2 (WP2-DEC-02): ProgramVersionLevel is the authoritative level/version
 * model — ordered levels unique per immutable program version, with optional
 * CEFR. A class's level must belong to the class's own program version: the
 * defineClass command enforces it (academic.class_level_version_mismatch) and
 * the classes_level_version_matches trigger refuses any direct cross-version
 * write the same way.
 */
final class ProgramVersionLevelFoundationTest extends TestCase
{
    use BuildsActors;

    private string $versionId;

    protected function setUp(): void
    {
        parent::setUp();
        $structure = app(MaintainAcademicStructure::class);
        $officer = $this->academicOfficer();
        $program = $structure->defineProgram($officer, 'Intensive English F2', 'f2-prog');
        $this->versionId = $structure->publishVersion(
            $officer,
            Program::query()->findOrFail($program['program_id']),
            'F2 base version',
            'f2-ver-1',
        )['version_id'];
    }

    public function test_levels_are_ordered_and_unique_per_program_version(): void
    {
        $structure = app(MaintainAcademicStructure::class);
        $officer = $this->academicOfficer();

        $first = $structure->defineLevel($officer, $this->versionId, 'beginner', 1, 'Beginner', 'A1', 'f2-lvl-1');
        $this->assertNotNull(DB::table('program_version_levels')->where('id', $first['level_id'])->first());

        $structure->defineLevel($officer, $this->versionId, 'intermediate', 2, 'Intermediate', 'B1', 'f2-lvl-2');

        $this->assertSame(2, ProgramVersionLevel::query()->where('program_version_id', $this->versionId)->count());
    }

    public function test_duplicate_level_key_or_ordinal_is_rejected(): void
    {
        $structure = app(MaintainAcademicStructure::class);
        $officer = $this->academicOfficer();
        $structure->defineLevel($officer, $this->versionId, 'beginner', 1, 'Beginner', 'A1', 'f2-lvl-a');

        try {
            $structure->defineLevel($officer, $this->versionId, 'beginner', 2, 'Dupe key', null, 'f2-lvl-b');
            $this->fail('A duplicate level_key must be rejected.');
        } catch (BusinessRejection $e) {
            $this->assertSame('academic.level_key_exists', $e->errorCode());
        }

        try {
            $structure->defineLevel($officer, $this->versionId, 'other', 1, 'Dupe ordinal', null, 'f2-lvl-c');
            $this->fail('A duplicate ordinal must be rejected.');
        } catch (BusinessRejection $e) {
            $this->assertSame('academic.level_ordinal_exists', $e->errorCode());
        }

        try {
            $structure->defineLevel($officer, $this->versionId, 'zero', 0, 'Zero ordinal', null, 'f2-lvl-d');
            $this->fail('A non-positive ordinal must be rejected.');
        } catch (BusinessRejection $e) {
            $this->assertSame('academic.level_ordinal_positive', $e->errorCode());
        }
    }

    public function test_a_class_level_must_belong_to_the_class_program_version(): void
    {
        $structure = app(MaintainAcademicStructure::class);
        $officer = $this->academicOfficer();
        $level = $structure->defineLevel($officer, $this->versionId, 'beginner', 1, 'Beginner', 'A1', 'f2-lvl-e');
        $periodId = $structure->definePeriod($officer, 'F2 Term', new CarbonImmutable('2026-09-01'), new CarbonImmutable('2026-12-18'), 'f2-period')['period_id'];
        $period = AcademicPeriod::query()->findOrFail($periodId);
        $structure->transitionPeriod($officer, $period, 'published', 'f2-period-pub');
        // Classes are born from an open offering of their level; open the
        // delivery surface for level 'beginner' before defining any class.
        $structure->declareBranchAvailability($officer, $this->bootstrapBranchId(), $level['level_id'], $periodId, 'f2-avail');
        $structure->openOffering($officer, $this->bootstrapBranchId(), $level['level_id'], $periodId, 200, 'f2-offering');

        // A second version whose levels do not include the first version's level.
        $version2 = $structure->publishVersion(
            $officer,
            Program::query()->where('name', 'Intensive English F2')->firstOrFail(),
            'F2 second version',
            'f2-ver-2',
        )['version_id'];

        // A class of version 1 bound to its own level is valid.
        $class = app(MaintainClass::class)->defineClass($officer, $this->versionId, $periodId, 20, 'f2-class-1', $level['level_id'], $this->bootstrapBranchId());
        $this->assertSame($level['level_id'], DB::table('classes')->where('id', $class['class_id'])->value('program_version_level_id'));

        // A class of version 2 cannot reference version 1's level: the define
        // command refuses the mismatch before any row is written.
        try {
            app(MaintainClass::class)->defineClass($officer, $version2, $periodId, 20, 'f2-class-2', $level['level_id'], $this->bootstrapBranchId());
            $this->fail('A class level from a different program version must be rejected.');
        } catch (BusinessRejection $e) {
            $this->assertSame('academic.class_level_version_mismatch', $e->errorCode());
        }

        // And the schema still refuses any direct rewrite of an
        // offering-established class level: provenance is immutable, so a
        // cross-version level write cannot bypass the domain command.
        $upper = $structure->defineLevel($officer, $version2, 'upper', 1, 'Upper', 'B1', 'f2-lvl-v2');
        $structure->declareBranchAvailability($officer, $this->bootstrapBranchId(), $upper['level_id'], $periodId, 'f2-avail-v2');
        $structure->openOffering($officer, $this->bootstrapBranchId(), $upper['level_id'], $periodId, 200, 'f2-offering-v2');
        $class2 = app(MaintainClass::class)->defineClass($officer, $version2, $periodId, 20, 'f2-class-3', $upper['level_id'], $this->bootstrapBranchId());
        // A rejected statement aborts the surrounding transaction, so this
        // attempt runs in its own savepoint and the assertions after it can
        // still read.
        DB::beginTransaction();
        try {
            DB::table('classes')->where('id', $class2['class_id'])
                ->update(['program_version_level_id' => $level['level_id']]);
            $this->fail('A class level from a different program version must be rejected.');
            DB::rollBack();
        } catch (QueryException $e) {
            DB::rollBack();
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }
}
