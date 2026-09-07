<?php

declare(strict_types=1);

namespace Tests\Canonical\Finance;

use App\Modules\Finance\Commands\MaintainChartOfAccounts;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\PostJournal;
use App\Modules\Finance\Commands\PostObligation;
use App\Modules\Finance\Commands\RecordReconciliation;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Models\Reconciliation;
use App\Modules\Payroll\Commands\MaintainPayrollPeriod;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

final class CoreIntegrityTest extends CanonicalTestCase
{
    private string $periodId;
    private string $arAccountId;
    private string $revenueAccountId;
    private string $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $accountant = $this->accountant();
        $this->arAccountId = DB::table('accounts')->where('code', '1100')->value('id');
        $this->revenueAccountId = DB::table('accounts')->where('code', '4000')->value('id');
        if ($this->arAccountId === null || $this->revenueAccountId === null) {
            throw new \RuntimeException('Canonical Finance fixture requires the standard seeded 1100/4000 accounts.');
        }
        $period = app(MaintainFinancialPeriod::class)->open($accountant, '2026-09', '2026-09-01', '2026-09-30', 'canon-fin-period');
        $this->periodId = $period['period_id'];
        $this->studentId = $this->newStudent()['student']->id;
    }

    private function accountant(): Actor
    {
        return $this->actorWith('canon-fin-accountant', [
            'finance.chart',
            'finance.period',
            'finance.obligation',
            'finance.journal',
            'finance.reconcile',
            'finance.reconcile_approve',
        ]);
    }

    public function test_obligation_lines_sum_exactly_and_obligation_amount_is_immutable(): void
    {
        $accountant = $this->accountant();
        $obligation = app(PostObligation::class)->post($accountant, FinancialPeriod::query()->findOrFail($this->periodId), $this->studentId, 'tuition', 'September tuition', [
            ['category' => 'tuition', 'amount' => '8000.00', 'source_ref' => 'price-list/v3'],
            ['category' => 'registration', 'amount' => '500.00', 'source_ref' => 'price-list/v3'],
        ], 'canon-fin-ob-1');
        $this->assertDatabaseHas('obligations', ['id' => $obligation['obligation_id'], 'original_amount' => '8500.00']);
        $this->assertSame(2, DB::table('obligation_lines')->where('obligation_id', $obligation['obligation_id'])->count());

        try {
            app(PostObligation::class)->post($accountant, FinancialPeriod::query()->findOrFail($this->periodId), $this->studentId, 'tuition', 'bad', [['category' => 'tuition', 'amount' => '0.00', 'source_ref' => 'x']], 'canon-fin-ob-2');
            $this->fail('zero-value obligation lines must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.obligation_line_amount', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement('UPDATE obligations SET original_amount = 1 WHERE id = ?', [$obligation['obligation_id']]);
    }

    public function test_balanced_journal_is_required_and_reversal_is_an_append_only_negation(): void
    {
        $accountant = $this->accountant();
        $obligation = app(PostObligation::class)->post($accountant, FinancialPeriod::query()->findOrFail($this->periodId), $this->studentId, 'tuition', 'September tuition', [['category' => 'tuition', 'amount' => '8500.00', 'source_ref' => 'price-list/v3']], 'canon-fin-ob-3');

        try {
            app(PostJournal::class)->post($accountant, FinancialPeriod::query()->findOrFail($this->periodId), 'obligation', $obligation['obligation_id'], 'charge posting', [
                ['account_id' => $this->arAccountId, 'direction' => 'debit', 'amount' => '8500.00'],
                ['account_id' => $this->revenueAccountId, 'direction' => 'credit', 'amount' => '8000.00'],
            ], 'canon-fin-j-1');
            $this->fail('an unbalanced journal must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.journal_unbalanced', $rejection->errorCode());
        }

        // PostObligation already journalizes the obligation, so a second
        // journal for the same source is correctly refused: an obligation has
        // exactly one journal, and corrections are appended as reversals.
        try {
            app(PostJournal::class)->post($accountant, FinancialPeriod::query()->findOrFail($this->periodId), 'obligation', $obligation['obligation_id'], 'duplicate journal attempt', [
                ['account_id' => $this->arAccountId, 'direction' => 'debit', 'amount' => '8500.00'],
                ['account_id' => $this->revenueAccountId, 'direction' => 'credit', 'amount' => '8500.00'],
            ], 'canon-fin-j-2');
            $this->fail('an obligation must not be journalized twice');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.obligation_already_journalized', $rejection->errorCode());
        }

        $journal = ['journal_id' => $obligation['journal_id']];
        $reversal = app(PostJournal::class)->reverse($accountant, Journal::query()->findOrFail($journal['journal_id']), 'charge voided after review', 'canon-fin-j-3');

        $this->assertDatabaseHas('journals', ['id' => $reversal['journal_id'], 'source_type' => 'journal', 'source_id' => $journal['journal_id']]);
        $debits = DB::table('journal_lines')->where('journal_id', $reversal['journal_id'])->where('direction', 'debit')->sum('amount');
        $credits = DB::table('journal_lines')->where('journal_id', $reversal['journal_id'])->where('direction', 'credit')->sum('amount');
        $this->assertEquals('8500.00', $debits);
        $this->assertEquals('8500.00', $credits);
        $this->assertSame('debit', (string) DB::table('journal_lines')->where('journal_id', $reversal['journal_id'])->where('account_id', $this->revenueAccountId)->value('direction'));

        $this->expectException(QueryException::class);
        DB::statement('UPDATE journal_lines SET amount = 1 WHERE journal_id = ?', [$journal['journal_id']]);
    }

    public function test_closed_financial_period_rejects_posting_and_cannot_be_reopened_by_direct_update(): void
    {
        $accountant = $this->accountant();
        app(MaintainFinancialPeriod::class)->close($accountant, FinancialPeriod::query()->findOrFail($this->periodId), 'canon-fin-period-close');

        try {
            app(PostObligation::class)->post($accountant, FinancialPeriod::query()->findOrFail($this->periodId), $this->studentId, 'tuition', 'late', [['category' => 'tuition', 'amount' => '100.00', 'source_ref' => 'x']], 'canon-fin-ob-closed');
            $this->fail('closed periods must reject obligations');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.period_not_open', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement("UPDATE financial_periods SET lifecycle_state = 'open' WHERE id = ?", [$this->periodId]);
    }

    public function test_financial_period_closure_respects_open_payroll_periods(): void
    {
        $accountant = $this->accountant();
        $payroll = $this->actorWith('canon-fin-payroll', ['payroll.period']);
        app(MaintainPayrollPeriod::class)->open($payroll, '2026-10', '2026-10-01', '2026-10-31', 'canon-pay-open');
        $october = app(MaintainFinancialPeriod::class)->open($accountant, '2026-10', '2026-10-01', '2026-10-31', 'canon-fin-october');

        try {
            app(MaintainFinancialPeriod::class)->close($accountant, FinancialPeriod::query()->findOrFail($october['period_id']), 'canon-fin-october-close');
            $this->fail('overlapping open payroll periods must block finance closure');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.period_payroll_open', $rejection->errorCode());
        }

        app(MaintainPayrollPeriod::class)->close($payroll, PayrollPeriod::query()->where('period_key', '2026-10')->firstOrFail(), 'canon-pay-close');
        app(MaintainFinancialPeriod::class)->close($accountant, FinancialPeriod::query()->findOrFail($october['period_id']), 'canon-fin-october-close-2');
        $this->assertDatabaseHas('financial_periods', ['id' => $october['period_id'], 'lifecycle_state' => 'closed']);
    }

    public function test_reconciliation_requires_explanation_for_variance_and_independent_approval(): void
    {
        $observer = $this->actorWith('canon-fin-recon-observer', ['finance.reconcile', 'finance.reconcile_approve']);
        $approver = $this->actorWith('canon-fin-recon-approver', ['finance.reconcile_approve']);

        try {
            app(RecordReconciliation::class)->observe($observer, FinancialPeriod::query()->findOrFail($this->periodId), 'ar-subledger', '8500.00', '8400.00', null, 'canon-fin-rec-1');
            $this->fail('variance without explanation must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.reconciliation_explanation', $rejection->errorCode());
        }

        $reconciliation = app(RecordReconciliation::class)->observe($observer, FinancialPeriod::query()->findOrFail($this->periodId), 'ar-subledger', '8500.00', '8400.00', 'cash payment not yet journalled', 'canon-fin-rec-2');
        $this->assertDatabaseHas('reconciliations', ['id' => $reconciliation['reconciliation_id'], 'variance' => '-100.00', 'lifecycle_state' => 'draft']);

        try {
            app(RecordReconciliation::class)->observe($observer, FinancialPeriod::query()->findOrFail($this->periodId), 'ar-subledger', '8500.00', '8400.00', 'again', 'canon-fin-rec-3');
            $this->fail('one observation per period and subject must be enforced');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.reconciliation_exists', $rejection->errorCode());
        }

        try {
            app(RecordReconciliation::class)->approve($observer, Reconciliation::query()->findOrFail($reconciliation['reconciliation_id']), 'canon-fin-rec-4');
            $this->fail('the observer must not approve their own reconciliation');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('finance.reconciliation_not_independent', $denial->errorCode());
        }

        app(RecordReconciliation::class)->approve($approver, Reconciliation::query()->findOrFail($reconciliation['reconciliation_id']), 'canon-fin-rec-5');
        $this->assertDatabaseHas('reconciliations', ['id' => $reconciliation['reconciliation_id'], 'lifecycle_state' => 'approved']);

        $this->expectException(QueryException::class);
        DB::statement('UPDATE reconciliations SET variance = 0 WHERE id = ?', [$reconciliation['reconciliation_id']]);
    }

    public function test_account_codes_are_unique_type_safe_and_immutable(): void
    {
        $accountant = $this->accountant();
        try {
            app(MaintainChartOfAccounts::class)->define($accountant, '1100', 'Duplicate', 'asset', 'canon-fin-dup-code');
            $this->fail('duplicate account codes must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.account_code_exists', $rejection->errorCode());
        }

        try {
            app(MaintainChartOfAccounts::class)->define($accountant, '1200', 'Bad Type', 'profit', 'canon-fin-bad-type');
            $this->fail('unknown account types must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.account_type_unknown', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement('UPDATE accounts SET name = ? WHERE code = ?', ['Forged Name', '1100']);
    }

    public function test_unprivileged_obligation_posting_is_denied_and_persists_nothing(): void
    {
        $nobody = $this->actorWith('canon-fin-nobody', []);

        $this->expectException(AuthorizationDenied::class);
        app(PostObligation::class)->post($nobody, FinancialPeriod::query()->findOrFail($this->periodId), $this->studentId, 'tuition', 'probe', [
            ['category' => 'tuition', 'amount' => '10.00', 'source_ref' => 'x'],
        ], 'canon-fin-negative');

        $this->assertDatabaseHas('audit_events', ['operation' => 'finance.obligation.post.denied', 'actor_id' => 'canon-fin-nobody']);
        $this->assertDatabaseMissing('obligations', ['student_id' => $this->studentId]);
    }
}
