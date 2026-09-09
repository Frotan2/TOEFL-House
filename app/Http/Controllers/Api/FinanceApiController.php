<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AttemptedOperation;
use App\Modules\Finance\Commands\AllocateFunds;
use App\Modules\Finance\Commands\AllocatePayment;
use App\Modules\Finance\Commands\MaintainCashDrawer;
use App\Modules\Finance\Commands\MaintainChartOfAccounts;
use App\Modules\Finance\Commands\MaintainDiscount;
use App\Modules\Finance\Commands\MaintainEmploymentSettlement;
use App\Modules\Finance\Commands\MaintainExpense;
use App\Modules\Finance\Commands\MaintainFinancialCorrection;
use App\Modules\Finance\Commands\MaintainFinancialCredit;
use App\Modules\Finance\Commands\MaintainFinancialGateException;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\MaintainInstallmentPlan;
use App\Modules\Finance\Commands\MaintainScholarshipAward;
use App\Modules\Finance\Commands\PostJournal;
use App\Modules\Finance\Commands\PostObligation;
use App\Modules\Finance\Commands\RecognizePayrollLiability;
use App\Modules\Finance\Commands\RecordPayment;
use App\Modules\Finance\Commands\RecordReconciliation;
use App\Modules\Finance\Commands\RefundPayment;
use App\Modules\Finance\Commands\RevokeFinancialCoverage;
use App\Modules\Finance\Models\CashDrawer;
use App\Modules\Finance\Models\Discount;
use App\Modules\Finance\Models\EnrollmentInstallmentPlan;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FinancialCorrection;
use App\Modules\Finance\Models\FinancialCoverageRevocation;
use App\Modules\Finance\Models\FinancialCredit;
use App\Modules\Finance\Models\FinancialGateException;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\FundAllocation;
use App\Modules\Finance\Models\FundingSource;
use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Models\Obligation;
use App\Modules\Finance\Models\ObligationLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Models\Reconciliation;
use App\Modules\Finance\Models\Refund;
use App\Modules\Finance\Models\ScholarshipAward;
use App\Modules\Finance\Queries\GeneralLedgerQuery;
use App\Modules\Hr\Models\Employment;
use App\Modules\Identity\Models\Person;
use App\Modules\Payroll\Models\SettlementProposal;
use App\Support\Errors\AuthorizationDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON interface for the money surface (delegates to the same commands). */
final class FinanceApiController extends Controller
{
    public function obligations(): JsonResponse
    {
        $visible = $this->authorizedBranches('finance.obligation');
        $obligations = Obligation::query()
            ->where(function ($query) use ($visible): void {
                $query->whereIn('current_home_branch_id', $visible)
                    ->orWhere(function ($query) use ($visible): void {
                        $query->whereNull('current_home_branch_id')->whereIn('originating_branch_id', $visible);
                    });
            })
            ->orderByDesc('id')->limit(200)->get();

        return response()->json(['obligations' => $obligations]);
    }

    public function payments(): JsonResponse
    {
        $visible = $this->authorizedBranches('finance.payment');
        $payments = Payment::query()
            ->where(function ($query) use ($visible): void {
                $query->whereIn('current_home_branch_id', $visible)
                    ->orWhere(function ($query) use ($visible): void {
                        $query->whereNull('current_home_branch_id')->whereIn('originating_branch_id', $visible);
                    });
            })
            ->orderByDesc('received_on')->limit(200)->get();

        return response()->json(['payments' => $payments]);
    }

    public function approveEmploymentSettlement(string $proposalId): JsonResponse
    {
        $employmentIds = Employment::query()
            ->whereIn('person_id', Person::query()->whereIn('home_branch_id', $this->authorizedBranches('finance.employment_settlement'))->select('id'))
            ->select('id');
        $proposal = SettlementProposal::query()->whereKey($proposalId)->whereIn('employment_id', $employmentIds)->firstOrFail();
        $employment = Employment::query()->whereIn('id', $employmentIds)->findOrFail($proposal->employment_id);
        $result = app(MaintainEmploymentSettlement::class)->record(
            $this->actor(),
            $employment,
            (string) $proposal->id,
            (string) $proposal->amount,
            (string) $proposal->basis,
            (string) $proposal->prepared_by,
            $this->idempotencyKey('finance.employment-settlement.record'),
        );

        return response()->json(['status' => 'recorded', ...$result]);
    }

    public function recognizePayrollLiability(Request $request): JsonResponse
    {
        $input = $request->validate([
            'source_type' => ['required', 'in:payroll_result'],
            'source_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'signed_money'],
            'evidence_ref' => ['required', 'string', 'max:255'],
        ]);
        $result = app(RecognizePayrollLiability::class)->recognize(
            $this->actor(), $input['source_type'], $input['source_id'], $input['amount'], $input['evidence_ref'],
            $this->idempotencyKey('finance.payroll-liability.recognize'),
        );

        return response()->json(['status' => 'recognized', ...$result], 201);
    }

    public function record(Request $request): JsonResponse
    {
        $input = $request->validate([
            'period_id' => ['required', 'string'],
            'student_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'money', 'gt:0'],
            'method' => ['required', 'string', 'max:40'],
            'payer_ref' => ['required', 'string', 'max:120'],
            'received_on' => ['required', 'date'],
        ]);

        app(RecordPayment::class)->record(
            $this->actor(),
            FinancialPeriod::query()->findOrFail((string) $input['period_id']),
            $input['student_id'],
            $input['amount'],
            $input['method'],
            $input['payer_ref'],
            $input['received_on'],
            $this->idempotencyKey('finance.payment'),
        );

        return response()->json(['status' => 'recorded'], 201);
    }

    public function postObligation(Request $request): JsonResponse
    {
        $input = $request->validate([
            'period_id' => ['required', 'string'], 'student_id' => ['required', 'string'],
            'source' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'max:1000'],
            'category' => ['required', 'string', 'max:120'], 'amount' => ['required', 'numeric', 'money', 'gt:0'],
            'source_ref' => ['required', 'string', 'max:120'], 'offering_id' => ['nullable', 'string'],
        ]);
        $result = app(PostObligation::class)->post(
            $this->actor(), FinancialPeriod::query()->findOrFail((string) $input['period_id']), $input['student_id'],
            $input['source'], $input['reason'], [[
                'category' => $input['category'], 'amount' => $input['amount'], 'source_ref' => $input['source_ref'],
            ]], $this->idempotencyKey('finance.obligation.post'), $input['offering_id'] ?? null,
        );

        return response()->json(['status' => 'posted', ...$result], 201);
    }

    public function allocatePayment(Request $request, string $obligationId): JsonResponse
    {
        $input = $request->validate([
            'payment_id' => ['required', 'string'], 'amount' => ['required', 'numeric', 'money', 'gt:0'],
        ]);
        $result = app(AllocatePayment::class)->allocate(
            $this->actor(), Payment::query()->findOrFail((string) $input['payment_id']),
            Obligation::query()->findOrFail($obligationId), $input['amount'],
            $this->idempotencyKey('finance.allocate'),
        );

        return response()->json(['status' => 'allocated', ...$result], 201);
    }
}
