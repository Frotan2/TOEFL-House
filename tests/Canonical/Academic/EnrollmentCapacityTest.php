<?php

declare(strict_types=1);

namespace Tests\Canonical\Academic;

use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Models\Enrollment;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Enrollment is the membership authority. A seat claim is only real once it is
 * activated by an approver, and activation is bounded by class capacity.
 *
 * These tests exercise the full delivery chain (open offering -> class ->
 * teacher -> active class) so the assertions are about enrollment rules rather
 * than about setup.
 */
final class EnrollmentCapacityTest extends CanonicalTestCase
{
    private Actor $officer;

    private Actor $approver;

    /** @var array<string, string> */
    private array $delivery;

    /** @var array<string, string> */
    private array $roomy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = $this->actorWith('canon-enrol-officer', [
            'academic.structure', 'academic.schedule', 'academic.teacher_manage',
            'academic.enroll',
        ]);
        $this->approver = $this->actorWith('canon-enrol-approver', ['academic.enroll_approve']);

        // Capacity 1 makes the capacity boundary observable with two students.
        $this->delivery = $this->newActiveClass($this->officer, 'canon-enrol', 1);
        // A separate roomy class for tests that are not about capacity.
        $this->roomy = $this->newActiveClass($this->officer, 'canon-enrol-roomy', 5);
    }

    public function test_the_delivery_chain_produces_an_active_class(): void
    {
        $this->assertDatabaseHas('classes', [
            'id' => $this->delivery['class_id'],
            'lifecycle_state' => 'active',
        ]);
    }

    public function test_a_requested_seat_is_not_yet_an_active_claim(): void
    {
        $student = $this->newStudent()['student'];

        $enrollment = $this->newSeatRequest(
            $this->officer,
            $student->id,
            $this->roomy['class_id'],
            'canon-enrol-request',
        );

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment['enrollment_id'],
            'lifecycle_state' => 'requested',
        ]);
    }

    public function test_activating_a_seat_requires_the_approval_capability(): void
    {
        $student = $this->newStudent()['student'];
        $enrollment = $this->newSeatRequest(
            $this->officer,
            $student->id,
            $this->roomy['class_id'],
            'canon-enrol-authz',
        );

        // The requester may ask for a seat but must not approve their own.
        $this->expectException(AuthorizationDenied::class);

        app(MaintainEnrollment::class)->activate(
            $this->officer,
            Enrollment::query()->findOrFail($enrollment['enrollment_id']),
            'canon-enrol-self-approve',
        );
    }

    public function test_a_requested_seat_already_claims_capacity(): void
    {
        $classId = $this->delivery['class_id'];

        // Capacity is asserted at request time against `requested`, `active`
        // and `frozen` claims (EnrollmentConstraints::assertCapacity), so a
        // pending request holds the seat. This is the rule the system actually
        // enforces: capacity is not an activation-time check.
        $this->newSeatRequest(
            $this->officer,
            $this->newStudent()['student']->id,
            $classId,
            'canon-enrol-cap-1',
        );

        $overflowRejected = false;
        try {
            $this->newSeatRequest(
                $this->officer,
                $this->newStudent()['student']->id,
                $classId,
                'canon-enrol-cap-2',
            );
        } catch (BusinessRejection) {
            $overflowRejected = true;
        }

        $liveSeats = DB::table('enrollments')
            ->where('class_id', $classId)
            ->whereIn('lifecycle_state', ['requested', 'active', 'frozen'])
            ->count();

        $this->assertTrue($overflowRejected, 'A seat request beyond capacity must be rejected.');
        $this->assertSame(1, $liveSeats, 'Live seat claims must never exceed the class capacity.');
    }

    public function test_activating_the_only_claim_keeps_seats_within_capacity(): void
    {
        $classId = $this->roomy['class_id'];

        $seat = $this->newSeatRequest(
            $this->officer,
            $this->newStudent()['student']->id,
            $classId,
            'canon-enrol-activate',
        );

        app(MaintainEnrollment::class)->activate(
            $this->approver,
            Enrollment::query()->findOrFail($seat['enrollment_id']),
            'canon-enrol-activate-approve',
        );

        $this->assertDatabaseHas('enrollments', [
            'id' => $seat['enrollment_id'],
            'lifecycle_state' => 'active',
        ]);
        $this->assertSame(
            1,
            DB::table('enrollments')->where('class_id', $classId)->whereIn('lifecycle_state', ['requested', 'active', 'frozen'])->count(),
            'Activation must not increase the number of live seat claims.'
        );
    }
}
