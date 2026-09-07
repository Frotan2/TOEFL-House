<?php

declare(strict_types=1);

namespace Tests\Canonical\Access;

use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\PostObligation;
use App\Modules\Finance\Commands\RecordPayment;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Negative authorization.
 *
 * Authority is a server decision. These tests assert the *absence* of
 * capability is fatal, and that a denied command leaves no persisted trace —
 * denial must not be a partial write.
 *
 * Positive-path coverage lives with each domain; this class exists so that
 * every sensitive Finance verb has an explicit negative case.
 */
final class NegativeAuthorizationTest extends CanonicalTestCase
{
    private FinancialPeriod $period;

    private string $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $officer = $this->actorWith('canon-authz-officer', ['finance.period', 'finance.obligation']);
        $opened = app(MaintainFinancialPeriod::class)->open(
            $officer,
            'CANON-AUTHZ',
            '2026-01-01',
            '2026-12-31',
            'canon-authz-period',
        );
        $this->period = FinancialPeriod::query()->findOrFail($opened['period_id']);
        $this->studentId = $this->newStudent()['student']->id;
    }

    public function test_opening_a_financial_period_requires_the_period_capability(): void
    {
        $unprivileged = $this->actorWith('canon-authz-nobody', []);

        $this->expectException(AuthorizationDenied::class);

        app(MaintainFinancialPeriod::class)->open(
            $unprivileged,
            'CANON-DENIED',
            '2027-01-01',
            '2027-12-31',
            'canon-authz-denied-period',
        );
    }

    public function test_recording_a_payment_requires_the_payment_capability(): void
    {
        // Deliberately granted a neighbouring Finance capability, not this one:
        // proves the check is per-capability, not "any Finance authority".
        $wrongCapability = $this->actorWith('canon-authz-wrong', ['finance.obligation']);

        $this->expectException(AuthorizationDenied::class);

        app(RecordPayment::class)->record(
            $wrongCapability,
            $this->period,
            $this->studentId,
            '100.00',
            'cash',
            'payer-denied',
            '2026-06-01',
            'canon-authz-denied-payment',
        );
    }

    public function test_a_denied_command_persists_nothing(): void
    {
        $unprivileged = $this->actorWith('canon-authz-nobody-2', []);

        $before = DB::table('obligations')->count();

        try {
            app(PostObligation::class)->post(
                $unprivileged,
                $this->period,
                $this->studentId,
                'tuition',
                'should never persist',
                [['category' => 'tuition', 'source_ref' => 'canonical/denied', 'amount' => '500.00']],
                'canon-authz-denied-obligation',
            );
            $this->fail('An unprivileged actor must not be able to post an obligation.');
        } catch (AuthorizationDenied|BusinessRejection) {
            // Expected.
        }

        $this->assertSame(
            $before,
            DB::table('obligations')->count(),
            'A denied command must not leave a partial financial record.'
        );
    }
}
