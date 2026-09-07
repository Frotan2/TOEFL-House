<?php

declare(strict_types=1);

namespace Tests\Canonical\Finance;

use App\Modules\Finance\Commands\AllocatePayment;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\PostObligation;
use App\Modules\Finance\Commands\RecordPayment;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\Obligation;
use App\Modules\Finance\Models\Payment;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Finance is the sole monetary authority. These tests assert monetary truth
 * against persisted state, not against mocks:
 *
 *   - money is conserved (allocated never exceeds received)
 *   - allocation cannot exceed the obligation it settles
 *   - idempotency keys make a retried payment a single financial fact
 *   - a rejected command leaves no partial financial residue
 */
final class MonetaryIntegrityTest extends CanonicalTestCase
{
    private Actor $financeOfficer;

    private FinancialPeriod $period;

    private string $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financeOfficer = $this->actorWith('canon-finance-officer', [
            'finance.period', 'finance.obligation', 'finance.payment', 'finance.allocate',
        ]);

        $opened = app(MaintainFinancialPeriod::class)->open(
            $this->financeOfficer,
            'CANON-2026',
            '2026-01-01',
            '2026-12-31',
            'canon-period-open',
        );
        $this->period = FinancialPeriod::query()->findOrFail($opened['period_id']);

        $this->studentId = $this->newStudent()['student']->id;
    }

    public function test_allocation_cannot_exceed_the_obligation_it_settles(): void
    {
        $obligation = $this->postObligation('1000.00', 'canon-oblig-1');
        $payment = $this->recordPayment('1000.00', 'canon-pay-1');

        // Settling more than is owed would create money.
        $this->expectException(BusinessRejection::class);

        app(AllocatePayment::class)->allocate(
            $this->financeOfficer,
            Payment::query()->findOrFail($payment),
            Obligation::query()->findOrFail($obligation),
            '1500.00',
            'canon-alloc-over',
        );
    }

    public function test_allocated_never_exceeds_received_after_a_rejected_allocation(): void
    {
        $obligation = $this->postObligation('400.00', 'canon-oblig-2');
        $payment = $this->recordPayment('400.00', 'canon-pay-2');

        try {
            app(AllocatePayment::class)->allocate(
                $this->financeOfficer,
                Payment::query()->findOrFail($payment),
                Obligation::query()->findOrFail($obligation),
                '900.00',
                'canon-alloc-reject',
            );
        } catch (BusinessRejection) {
            // Expected: the command must reject and leave nothing behind.
        }

        $received = (float) DB::table('payments')->sum('amount');
        $allocated = (float) DB::table('payment_allocations')->sum('amount');

        $this->assertSame(
            0.0,
            $allocated,
            'A rejected allocation must not persist any allocated amount.'
        );
        $this->assertLessThanOrEqual(
            $received,
            $allocated,
            'Conservation: allocated may never exceed received.'
        );
    }

    public function test_a_retried_payment_with_the_same_idempotency_key_is_one_financial_fact(): void
    {
        $first = $this->recordPayment('250.00', 'canon-idem-key');
        $second = $this->recordPayment('250.00', 'canon-idem-key');

        $this->assertSame($first, $second, 'The same idempotency key must return the same payment.');
        $this->assertSame(1, DB::table('payments')->count(), 'A retry must not create a second payment.');
        $this->assertSame(
            250.0,
            (float) DB::table('payments')->sum('amount'),
            'A retry must not double the money received.'
        );
    }

    public function test_a_successful_allocation_is_conserved_and_provenanced(): void
    {
        $obligation = $this->postObligation('1000.00', 'canon-oblig-3');
        $payment = $this->recordPayment('400.00', 'canon-pay-3');

        app(AllocatePayment::class)->allocate(
            $this->financeOfficer,
            Payment::query()->findOrFail($payment),
            Obligation::query()->findOrFail($obligation),
            '400.00',
            'canon-alloc-ok',
        );

        $received = (float) DB::table('payments')->sum('amount');
        $allocated = (float) DB::table('payment_allocations')->sum('amount');

        $this->assertSame(400.0, $received);
        $this->assertSame(400.0, $allocated);
        $this->assertLessThanOrEqual($received, $allocated);

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment,
            'obligation_id' => $obligation,
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function postObligation(string $amount, string $key): string
    {
        $posted = app(PostObligation::class)->post(
            $this->financeOfficer,
            $this->period,
            $this->studentId,
            'tuition',
            'canonical fixture obligation',
            [['category' => 'tuition', 'source_ref' => 'canonical/'.$key, 'amount' => $amount]],
            $key,
        );

        return $posted['obligation_id'];
    }

    private function recordPayment(string $amount, string $key): string
    {
        $recorded = app(RecordPayment::class)->record(
            $this->financeOfficer,
            $this->period,
            $this->studentId,
            $amount,
            'cash',
            'payer-'.$key,
            '2026-06-01',
            $key,
        );

        return $recorded['payment_id'];
    }
}
