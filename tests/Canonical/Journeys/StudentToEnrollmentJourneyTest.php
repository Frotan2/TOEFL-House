<?php

declare(strict_types=1);

namespace Tests\Canonical\Journeys;

use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Models\Enrollment;
use App\Modules\Finance\Commands\AllocatePayment;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\PostObligation;
use App\Modules\Finance\Commands\RecordPayment;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\Obligation;
use App\Modules\Finance\Models\Payment;
use App\Support\Authorization\Actor;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Cross-domain journey: a real student reaching an active seat, with the
 * money that accompanies it.
 *
 *     Admission -> Student -> Delivery (offering/class/teacher)
 *               -> Seat request -> Activation
 *     Obligation -> Payment -> Allocation -> Balance
 *
 * The value of a journey test is that it crosses ownership boundaries:
 * Admissions owns the applicant decision, Academic owns the seat, Finance owns
 * the money, and none of them may quietly do another's job. Every assertion is
 * against persisted state.
 */
final class StudentToEnrollmentJourneyTest extends CanonicalTestCase
{
    private Actor $academicOfficer;

    private Actor $enrollmentApprover;

    private Actor $financeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicOfficer = $this->actorWith('canon-journey-academic', [
            'academic.structure', 'academic.schedule', 'academic.teacher_manage', 'academic.enroll',
        ]);
        $this->enrollmentApprover = $this->actorWith('canon-journey-approver', ['academic.enroll_approve']);
        $this->financeOfficer = $this->actorWith('canon-journey-finance', [
            'finance.period', 'finance.obligation', 'finance.payment', 'finance.allocate',
        ]);
    }

    public function test_a_student_reaches_an_active_seat_with_a_settled_balance(): void
    {
        // --- Admissions: a student exists only via the admission workflow ---
        $student = $this->newStudent()['student'];
        $this->assertDatabaseHas('students', ['id' => $student->id]);

        // --- Academic: a class only becomes active with a real teacher ------
        $delivery = $this->newActiveClass($this->academicOfficer, 'canon-journey', 3);
        $this->assertDatabaseHas('classes', [
            'id' => $delivery['class_id'],
            'lifecycle_state' => 'active',
        ]);

        // --- Academic: seat request then independent approval ---------------
        $seat = $this->newSeatRequest(
            $this->academicOfficer,
            $student->id,
            $delivery['class_id'],
            'canon-journey-seat',
        );
        $this->assertDatabaseHas('enrollments', [
            'id' => $seat['enrollment_id'],
            'lifecycle_state' => 'requested',
        ]);

        app(MaintainEnrollment::class)->activate(
            $this->enrollmentApprover,
            Enrollment::query()->findOrFail($seat['enrollment_id']),
            'canon-journey-activate',
        );
        $this->assertDatabaseHas('enrollments', [
            'id' => $seat['enrollment_id'],
            'student_id' => $student->id,
            'class_id' => $delivery['class_id'],
            'lifecycle_state' => 'active',
        ]);

        // --- Finance: the money for that seat, owned by Finance alone -------
        $period = FinancialPeriod::query()->findOrFail(
            app(MaintainFinancialPeriod::class)->open(
                $this->financeOfficer,
                'CANON-JOURNEY',
                '2026-01-01',
                '2026-12-31',
                'canon-journey-period',
            )['period_id']
        );

        $obligation = app(PostObligation::class)->post(
            $this->financeOfficer,
            $period,
            $student->id,
            'tuition',
            'journey tuition',
            [['category' => 'tuition', 'source_ref' => 'canonical/journey', 'amount' => '600.00']],
            'canon-journey-obligation',
        )['obligation_id'];

        $payment = app(RecordPayment::class)->record(
            $this->financeOfficer,
            $period,
            $student->id,
            '600.00',
            'cash',
            'payer-canon-journey',
            '2026-06-01',
            'canon-journey-payment',
        )['payment_id'];

        app(AllocatePayment::class)->allocate(
            $this->financeOfficer,
            Payment::query()->findOrFail($payment),
            Obligation::query()->findOrFail($obligation),
            '600.00',
            'canon-journey-allocate',
        );

        // --- Outcome: the balance settles and money is conserved ------------
        $received = (float) DB::table('payments')->where('student_id', $student->id)->sum('amount');
        $allocated = (float) DB::table('payment_allocations')->where('obligation_id', $obligation)->sum('amount');

        $this->assertSame(600.0, $received);
        $this->assertSame(600.0, $allocated);
        $this->assertLessThanOrEqual($received, $allocated, 'Allocated may never exceed received.');

        // The enrollment survived the financial activity unchanged: Finance
        // does not silently mutate Academic state.
        $this->assertDatabaseHas('enrollments', [
            'id' => $seat['enrollment_id'],
            'lifecycle_state' => 'active',
        ]);
    }
}
