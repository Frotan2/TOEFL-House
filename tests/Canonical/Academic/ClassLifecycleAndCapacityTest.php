<?php

declare(strict_types=1);

namespace Tests\Canonical\Academic;

use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Models\ClassModel;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Academic owns the delivery lifecycle. These tests assert the chain the
 * domain actually enforces:
 *
 *     program -> published version -> level
 *             -> published period -> branch availability -> open offering
 *             -> class -> teacher -> activation
 *
 * and the rules that protect it: a class cannot exist without an open
 * offering, capacity cannot exceed the offering, and lifecycle transitions
 * are not free-form.
 */
final class ClassLifecycleAndCapacityTest extends CanonicalTestCase
{
    private Actor $officer;

    /** @var array<string, string> */
    private array $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = $this->actorWith('canon-acad-officer', [
            'academic.structure', 'academic.schedule', 'academic.teacher_manage',
        ]);
        $this->chain = $this->newAcademicChain($this->officer, 'canon-acad', 10);
    }

    public function test_the_canonical_chain_produces_an_open_offering(): void
    {
        $this->assertDatabaseHas('offerings', [
            'id' => $this->chain['offering_id'],
            'lifecycle_state' => 'open',
        ]);
        $this->assertDatabaseHas('academic_periods', [
            'id' => $this->chain['period_id'],
            'lifecycle_state' => 'published',
        ]);
    }

    public function test_a_class_cannot_be_defined_without_an_open_offering(): void
    {
        // A published version and period are not sufficient: the domain
        // requires an offering for that branch, level and period.
        $orphan = $this->newAcademicChain($this->officer, 'canon-acad-orphan', 5);

        // Deliberately pair one chain's version with another chain's period so
        // no offering matches the combination.
        $this->expectException(BusinessRejection::class);

        app(MaintainClass::class)->defineClass(
            $this->officer,
            $this->chain['program_version_id'],
            $orphan['period_id'],
            2,
            'canon-class-orphan',
        );
    }

    public function test_class_capacity_cannot_exceed_its_offering_capacity(): void
    {
        // The offering above was opened with capacity 10.
        $this->expectException(BusinessRejection::class);

        app(MaintainClass::class)->defineClass(
            $this->officer,
            $this->chain['program_version_id'],
            $this->chain['period_id'],
            50,
            'canon-class-oversized',
        );
    }

    public function test_class_capacity_must_be_positive(): void
    {
        $this->expectException(BusinessRejection::class);

        app(MaintainClass::class)->defineClass(
            $this->officer,
            $this->chain['program_version_id'],
            $this->chain['period_id'],
            0,
            'canon-class-zero',
        );
    }

    public function test_a_class_is_born_planned_and_cannot_skip_to_active(): void
    {
        $class = app(MaintainClass::class)->defineClass(
            $this->officer,
            $this->chain['program_version_id'],
            $this->chain['period_id'],
            4,
            'canon-class-lifecycle',
        );

        $this->assertDatabaseHas('classes', [
            'id' => $class['class_id'],
            'lifecycle_state' => 'planned',
        ]);

        // planned -> active skips publication; the database guard rejects it.
        $this->expectException(\Throwable::class);

        app(MaintainClass::class)->transition(
            $this->officer,
            ClassModel::query()->findOrFail($class['class_id']),
            'active',
            'canon-class-illegal-transition',
        );
    }

    public function test_a_rejected_class_definition_persists_nothing(): void
    {
        $before = DB::table('classes')->count();

        try {
            app(MaintainClass::class)->defineClass(
                $this->officer,
                $this->chain['program_version_id'],
                $this->chain['period_id'],
                999,
                'canon-class-rejected',
            );
        } catch (BusinessRejection) {
            // Expected.
        }

        $this->assertSame(
            $before,
            DB::table('classes')->count(),
            'A rejected class definition must not leave a partial row.'
        );
    }
}
