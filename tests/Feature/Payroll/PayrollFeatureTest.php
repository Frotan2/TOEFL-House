<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Finance\Commands\MaintainEmploymentSettlement;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\PostJournal;
use App\Modules\Finance\Commands\RecognizePayrollLiability;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Models\PayrollLiabilityFact;
use App\Modules\Hr\Commands\MaintainContractVersion;
use App\Modules\Hr\Commands\MaintainEmployment;
use App\Modules\Hr\Models\ContractVersion;
use App\Modules\Hr\Models\Employment;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Branch;
use App\Modules\Payroll\Commands\ApprovePayrollResult;
use App\Modules\Payroll\Commands\CalculatePayroll;
use App\Modules\Payroll\Commands\MaintainPayrollPeriod;
use App\Modules\Payroll\Commands\SettleEmployment;
use App\Modules\Payroll\Models\PayrollCalculation;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\SettlementProposal;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Payroll mechanics on the single authoritative compensation path:
 * versioned contract resolution with calendar-day proration, held
 * contract-silent cases, recalculation supersession, approval SoD,
 * immutable Payroll evidence with Finance-owned liability recognition and
 * compensating journal reversals, period closure, termination settlement and
 * capability denials.
 */
final class PayrollFeatureTest extends TestCase
{
    use BuildsActors;

    private string $employmentId;

    private string $personId = 'pay-teacher-1';

    private string $periodId;

    private string $financialPeriodId;

    protected function setUp(): void
    {
        parent::setUp();
        // Create the branch first: a verified person is immutable, so the
        // home branch must be set on INSERT rather than by a later UPDATE.
        $branch = Branch::query()->create(['id' => RandomIdentifier::new(), 'name' => 'Payroll Settlement Branch', 'lifecycle_state' => 'active']);
        $this->attachBranchToBootstrapOrganization($branch->id);
        $this->personWithAuthority($this->personId, [], $branch->id);

        $manager = $this->grantedActor('pay-manager-1', ['hr.employ', 'hr.terminate', 'access.assign_position']);
        $employment = app(MaintainEmployment::class)->employ($manager, $this->personId, 'pay-emp-1');
        $this->employmentId = $employment['employment_id'];

        // Single authoritative path: Contract Version + Compensation Rule.
        $fm = $this->financeManager();
        $commands = app(MaintainContractVersion::class);
        $prepared = $commands->prepare($fm, Employment::query()->findOrFail($this->employmentId), 'contract/2026-09.pdf', null, '2026-09-01', '2026-09-30', 'pay-con-1');
        $version = ContractVersion::query()->findOrFail($prepared['version_id']);
        $commands->addRule($fm, $version, 'fixed_monthly', '40000.00', null, null, null, 'pay-r-fix');
        $commands->addRule($fm, $version, 'allowance', '2000.00', null, null, 'housing', 'pay-r-al');
        $commands->submit($fm, $version, 'pay-con-2');
        $commands->approve($this->generalManager(), $version, 'pay-con-3');

        app(MaintainEmployment::class)->hire($manager, Employment::query()->findOrFail($this->employmentId), '2026-09-01', 'pay-emp-2');

        $periodOpener = $this->grantedActor('pay-period-1', ['payroll.period']);
        $period = app(MaintainPayrollPeriod::class)->open($periodOpener, '2026-09', '2026-09-01', '2026-09-30', 'pay-per-1');
        $this->periodId = $period['period_id'];

        // Settlement recording is a Finance domain action: it requires one
        // open Finance financial period containing the record date.
        $financialPeriod = app(MaintainFinancialPeriod::class)->open($this->grantedActor('pay-fperiod-1', ['finance.period']), '2026-09', '2026-09-01', '2026-09-30', 'pay-fper-1');
        $this->financialPeriodId = $financialPeriod['period_id'];
    }

    private function financeManager(): Actor
    {
        return $this->grantedActor('pay-fm-1', ['hr.contract.prepare']);
    }

    private function generalManager(): Actor
    {
        return $this->grantedActor('pay-gm-1', ['hr.contract.approve']);
    }

    private function payrollPreparer(): Actor
    {
        return $this->grantedActor('pay-calc-1', ['payroll.calculate']);
    }

    public function test_calculation_snapshots_contract_version_rules_scale_and_proration_with_exact_amount(): void
    {
        $calculation = app(CalculatePayroll::class)->prepare($this->payrollPreparer(), PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-calc-1');

        $this->assertSame('prepared', $calculation['lifecycle_state']);
        /** @var PayrollCalculation $row */
        $row = PayrollCalculation::query()->findOrFail($calculation['calculation_id']);
        // 40000 fixed + 2000 allowance, full-period version: 30/30 days, no proration loss.
        $this->assertSame('42000.00', $row->base_amount);
        $this->assertSame('skill-scale-v1', $row->snapshot['formula']);
        $this->assertNotSame('', (string) $row->snapshot['contract_version_id']);
        $this->assertSame(1, $row->snapshot['version_no']);
        $this->assertNull($row->snapshot['scale_id']);
        $this->assertCount(2, $row->snapshot['rules']);
        $this->assertSame([], $row->snapshot['delivery']);
        $this->assertSame(30, $row->snapshot['proration']['period_days']);
        $this->assertSame(30, $row->snapshot['proration']['active_days']);
        /** @var list<array<string, mixed>> $additiveLines */
        $additiveLines = $row->snapshot['additive'] ?? [];
        $fixed = collect($additiveLines)->firstWhere('method', 'fixed_monthly');
        $this->assertSame('40000.00', $fixed['contract_amount'] ?? null);
        $this->assertSame('40000.00', $fixed['amount']);
        $this->assertSame(30, $fixed['active_days']);
        $this->assertSame(30, $fixed['period_days']);
        $allowance = collect($additiveLines)->firstWhere('method', 'allowance');
        $this->assertSame('housing', $allowance['label'] ?? null);
        $this->assertSame('2000.00', $allowance['amount'] ?? null);
    }

    public function test_contract_silent_period_holds_the_calculation_and_blocks_closure(): void
    {
        // The version window ends 2026-09-30: October has no in-force version.
        $october = app(MaintainPayrollPeriod::class)->open($this->grantedActor('pay-period-1', ['payroll.period']), '2026-10', '2026-10-01', '2026-10-31', 'pay-per-oct');

        $calculation = app(CalculatePayroll::class)->prepare($this->payrollPreparer(), PayrollPeriod::query()->findOrFail($october['period_id']), Employment::query()->findOrFail($this->employmentId), 'pay-calc-2');
        $this->assertSame('held', $calculation['lifecycle_state']);
        $this->assertDatabaseHas('payroll_calculations', ['id' => $calculation['calculation_id'], 'lifecycle_state' => 'held']);
        /** @var PayrollCalculation $heldRow */
        $heldRow = PayrollCalculation::query()->findOrFail($calculation['calculation_id']);
        $this->assertStringContainsString('contract-silent', (string) $heldRow->held_reason);
        $this->assertSame('0.00', $heldRow->base_amount);

        $closer = $this->grantedActor('pay-period-1', ['payroll.period']);
        try {
            app(MaintainPayrollPeriod::class)->close($closer, PayrollPeriod::query()->findOrFail($october['period_id']), 'pay-per-2');
            $this->fail('closure must be blocked while a contract-silent calculation is held');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.period_close_held', $rejection->errorCode());
        }

        $approver = $this->grantedActor('pay-approve-1', ['payroll.approve']);
        try {
            app(ApprovePayrollResult::class)->approve($approver, PayrollCalculation::query()->findOrFail($calculation['calculation_id']), 'pay-calc-3');
            $this->fail('a held calculation can never be approved');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.calculation_not_prepared', $rejection->errorCode());
        }
    }

    public function test_recalculation_supersedes_and_retains_history(): void
    {
        $first = app(CalculatePayroll::class)->prepare($this->payrollPreparer(), PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-calc-4');

        // A later recalculation for the same period supersedes the prior
        // calculation and retains it as history.
        $second = app(CalculatePayroll::class)->prepare($this->payrollPreparer(), PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-calc-5');
        $this->assertNotSame($first['calculation_id'], $second['calculation_id']);
        $this->assertDatabaseHas('payroll_calculations', ['id' => $first['calculation_id'], 'lifecycle_state' => 'superseded']);
        $this->assertDatabaseHas('payroll_calculations', ['id' => $second['calculation_id'], 'lifecycle_state' => 'prepared']);
        /** @var PayrollCalculation $row */
        $row = PayrollCalculation::query()->findOrFail($second['calculation_id']);
        $this->assertSame('42000.00', $row->base_amount);
        /** @var PayrollCalculation $firstRow */
        $firstRow = PayrollCalculation::query()->findOrFail($first['calculation_id']);
        $this->assertSame('42000.00', $firstRow->base_amount);
        $this->assertSame($row->snapshot['contract_version_id'], $firstRow->snapshot['contract_version_id']);
    }

    public function test_approval_sod_and_immutable_result_with_finance_owned_liability_reversal(): void
    {
        $preparer = $this->grantedActor('pay-calc-1', ['payroll.calculate', 'payroll.approve']);
        $calculation = app(CalculatePayroll::class)->prepare($preparer, PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-calc-6');

        $approver = $this->grantedActor('pay-approve-1', ['payroll.approve']);
        try {
            app(ApprovePayrollResult::class)->approve($preparer, PayrollCalculation::query()->findOrFail($calculation['calculation_id']), 'pay-res-1');
            $this->fail('the preparer may not approve');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('payroll.approval_not_independent', $denial->errorCode());
        }

        $beneficiary = $this->grantedActor($this->personId, ['payroll.approve']);
        try {
            app(ApprovePayrollResult::class)->approve($beneficiary, PayrollCalculation::query()->findOrFail($calculation['calculation_id']), 'pay-res-2');
            $this->fail('the beneficiary may never approve their own payroll');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('payroll.beneficiary', $denial->errorCode());
        }

        $result = app(ApprovePayrollResult::class)->approve($approver, PayrollCalculation::query()->findOrFail($calculation['calculation_id']), 'pay-res-3');
        $this->assertDatabaseHas('payroll_results', ['id' => $result['result_id'], 'amount' => '42000.00', 'lifecycle_state' => 'approved']);
        $this->assertDatabaseHas('payroll_calculations', ['id' => $calculation['calculation_id'], 'lifecycle_state' => 'resulted']);

        try {
            app(ApprovePayrollResult::class)->approve($approver, PayrollCalculation::query()->findOrFail($calculation['calculation_id']), 'pay-res-4');
            $this->fail('a consumed calculation cannot produce a second result');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.calculation_not_prepared', $rejection->errorCode());
        }

        // Payroll ends at immutable approved evidence. A separate Finance actor
        // recognizes the exact liability and the Finance ledger appends an
        // immutable compensating reversal when a correction is required.
        $finance = $this->grantedActor('pay-finance-liability-1', ['finance.payroll_liability', 'finance.journal']);
        $recognized = app(RecognizePayrollLiability::class)->recognize(
            $finance,
            'payroll_result',
            $result['result_id'],
            '42000.00',
            'payroll/result/'.$result['result_id'],
            'pay-liability-1',
        );
        $this->assertFalse($recognized['duplicate']);
        $this->assertDatabaseHas('payroll_liability_facts', [
            'id' => $recognized['liability_id'],
            'source_type' => 'payroll_result',
            'source_id' => $result['result_id'],
            'amount' => '42000.00',
            'recognized_by' => $finance->actorId,
        ]);
        $liability = PayrollLiabilityFact::query()->findOrFail($recognized['liability_id']);
        $original = Journal::query()
            ->where('source_type', 'payroll_liability')
            ->where('source_id', $liability->id)
            ->firstOrFail();

        $reversal = app(PostJournal::class)->reverse($finance, $original, 'recognized payroll liability reversed after evidence review', 'pay-journal-reversal-1');
        $this->assertDatabaseHas('journals', [
            'id' => $reversal['journal_id'],
            'source_type' => 'journal',
            'source_id' => $original->id,
            'reversal_of_id' => $original->id,
        ]);
        try {
            app(PostJournal::class)->reverse($finance, $original, 'a second reversal must be impossible', 'pay-journal-reversal-2');
            $this->fail('a Finance journal may have only one compensating reversal');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.journal_already_reversed', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement('UPDATE payroll_results SET amount = 999999 WHERE id = ?', [$result['result_id']]);
    }

    public function test_closed_periods_reject_mutation_on_their_respective_authorities(): void
    {
        $preparer = $this->payrollPreparer();
        $calculation = app(CalculatePayroll::class)->prepare($preparer, PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-calc-7');
        $approver = $this->grantedActor('pay-approve-1', ['payroll.approve']);
        $result = app(ApprovePayrollResult::class)->approve($approver, PayrollCalculation::query()->findOrFail($calculation['calculation_id']), 'pay-res-5');
        $finance = $this->grantedActor('pay-finance-liability-2', ['finance.payroll_liability', 'finance.journal']);
        $recognized = app(RecognizePayrollLiability::class)->recognize(
            $finance,
            'payroll_result',
            $result['result_id'],
            '42000.00',
            'payroll/result/'.$result['result_id'],
            'pay-liability-2',
        );
        $original = Journal::query()
            ->where('source_type', 'payroll_liability')
            ->where('source_id', $recognized['liability_id'])
            ->firstOrFail();

        $closer = $this->grantedActor('pay-period-1', ['payroll.period']);
        app(MaintainPayrollPeriod::class)->close($closer, PayrollPeriod::query()->findOrFail($this->periodId), 'pay-per-3');
        $financeCloser = $this->grantedActor('pay-finance-period-closer', ['finance.period']);
        app(MaintainFinancialPeriod::class)->close($financeCloser, FinancialPeriod::query()->findOrFail($this->financialPeriodId), 'pay-finance-period-close-1');

        // Payroll closure blocks new Payroll calculations; financial closure
        // independently blocks the Finance correction path.
        try {
            app(PostJournal::class)->reverse($finance, $original, 'late reversal after financial close', 'pay-journal-reversal-after-close');
            $this->fail('a closed Finance period must reject a payroll-liability journal reversal');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.period_not_open', $rejection->errorCode());
        }

        try {
            app(CalculatePayroll::class)->prepare($preparer, PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-calc-8');
            $this->fail('a closed Payroll period must reject new calculations');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.period_not_open', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement("UPDATE payroll_periods SET lifecycle_state = 'open' WHERE id = ?", [$this->periodId]);
    }

    public function test_settlement_is_staged_with_termination_clearances_and_two_sessions(): void
    {
        $hrManager = $this->grantedActor('pay-manager-1', ['hr.employ', 'hr.terminate', 'access.assign_position']);
        $financeClearer = $this->grantedActor('pay-finance-1', ['payroll.clear_finance']);
        $hrClearer = $this->grantedActor('pay-hr-clear-1', ['payroll.clear_hr']);
        $preparer = $this->grantedActor('pay-settle-1', ['payroll.settle']);
        $settleApprover = $this->grantedActor('pay-settle-2', ['finance.employment_settlement']);
        $employment = fn (): Employment => Employment::query()->findOrFail($this->employmentId);

        try {
            app(SettleEmployment::class)->propose($preparer, $employment(), '5000.00', 'remaining balance', 'pay-set-1');
            $this->fail('settlement proposal requires a terminated employment');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.settlement_requires_termination', $rejection->errorCode());
        }

        app(MaintainEmployment::class)->terminate($hrManager, $employment(), '2026-10-01', 'contract ended', 'pay-emp-3');

        try {
            app(SettleEmployment::class)->propose($preparer, $employment(), '5000.00', 'remaining balance', 'pay-set-2');
            $this->fail('settlement proposal requires both clearances');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.settlement_requires_clearance', $rejection->errorCode());
        }

        app(SettleEmployment::class)->clear($hrClearer, $employment(), 'hr', 'no outstanding HR items', 'pay-cl-1');
        app(SettleEmployment::class)->clear($financeClearer, $employment(), 'finance', 'accounts reconciled', 'pay-cl-2');

        $proposal = app(SettleEmployment::class)->propose($preparer, $employment(), '5000.00', 'final dues per ledger review', 'pay-set-3');
        $proposalModel = SettlementProposal::query()->findOrFail($proposal['proposal_id']);

        try {
            app(SettleEmployment::class)->propose($preparer, $employment(), '5000.00', 'again', 'pay-set-9');
            $this->fail('a second open proposal must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.settlement_proposal_exists', $rejection->errorCode());
        }

        try {
            app(MaintainEmploymentSettlement::class)->record(
                $preparer,
                $employment(),
                (string) $proposalModel->id,
                (string) $proposalModel->amount,
                (string) $proposalModel->basis,
                (string) $proposalModel->prepared_by,
                'pay-set-4',
            );
            $this->fail('the preparer cannot approve their own proposal');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('finance.employment_settlement_not_independent', $denial->errorCode());
        }

        $settlement = app(MaintainEmploymentSettlement::class)->record(
            $settleApprover,
            $employment(),
            (string) $proposalModel->id,
            (string) $proposalModel->amount,
            (string) $proposalModel->basis,
            (string) $proposalModel->prepared_by,
            'pay-set-5',
        );
        $this->assertDatabaseHas('employment_settlements', ['id' => $settlement['settlement_id'], 'amount' => '5000.00', 'approved_by' => $settleApprover->actorId]);
        $this->assertDatabaseHas('settlement_proposals', ['id' => $proposalModel->id, 'lifecycle_state' => 'approved', 'approved_by' => $settleApprover->actorId]);

        try {
            app(MaintainEmploymentSettlement::class)->record(
                $settleApprover,
                $employment(),
                (string) $proposalModel->id,
                (string) $proposalModel->amount,
                (string) $proposalModel->basis,
                (string) $proposalModel->prepared_by,
                'pay-set-6',
            );
            $this->fail('an approved proposal cannot be approved again');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.employment_settlement_proposal_invalid', $rejection->errorCode());
        }

        try {
            app(SettleEmployment::class)->propose($preparer, $employment(), '6000.00', 'again', 'pay-set-7');
            $this->fail('a settled employment cannot carry a new proposal');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('payroll.settlement_exists', $rejection->errorCode());
        }

        $this->expectException(QueryException::class);
        DB::statement('UPDATE employment_settlements SET amount = 999999 WHERE id = ?', [$settlement['settlement_id']]);
    }

    public function test_unprivileged_calculation_is_denied_and_audited(): void
    {
        $nobody = $this->actorWithoutAnyCapability('pay-nobody');

        $this->expectException(AuthorizationDenied::class);
        app(CalculatePayroll::class)->prepare($nobody, PayrollPeriod::query()->findOrFail($this->periodId), Employment::query()->findOrFail($this->employmentId), 'pay-neg-1');

        $this->assertDatabaseHas('audit_events', ['operation' => 'payroll.calculation.prepare.denied', 'actor_id' => 'pay-nobody']);
        $this->assertDatabaseMissing('payroll_calculations', ['period_id' => $this->periodId]);
    }
}
