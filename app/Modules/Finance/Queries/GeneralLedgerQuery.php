<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Finance\Models\Discount;
use App\Modules\Finance\Models\EmploymentSettlement;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FinancialCorrection;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\FundAllocation;
use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Models\Obligation;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PayrollLiabilityFact;
use App\Modules\Finance\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finance's authoritative general-ledger read model.
 *
 * Journal lines are the double-entry accounting truth; source facts are the
 * operational detail that resolves to those lines. This query returns the GL
 * as an accountant expects it — a trial balance by account, the detail ledger,
 * and a completeness proof that every money fact is journalized. No other
 * bounded context reimplements the ledger arithmetic.
 */
final class GeneralLedgerQuery
{
    /** @return array{totals: array{debit: string, credit: string, net: string}, accounts: array<int, array{account_id: string, code: string, name: string, type: string, debit: string, credit: string, net: string}>, balanced: bool} */
    public function trialBalance(?string $periodId = null, ?string $organizationId = null): array
    {
        $query = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->selectRaw('a.id AS account_id, a.code AS code, a.name AS name, a.type AS type')
            ->selectRaw('COALESCE(SUM(jl.amount) FILTER (WHERE jl.direction = \'debit\'), 0) AS debit')
            ->selectRaw('COALESCE(SUM(jl.amount) FILTER (WHERE jl.direction = \'credit\'), 0) AS credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code');

        if ($periodId !== null && $periodId !== '') {
            $query->where('j.period_id', $periodId);
        }
        if ($organizationId !== null && $organizationId !== '') {
            $query->where('j.organization_id', $organizationId);
        }

        $accounts = [];
        $totalDebit = '0.00';
        $totalCredit = '0.00';
        foreach ($query->get() as $row) {
            $net = bcsub((string) $row->debit, (string) $row->credit, 2);
            $totalDebit = bcadd($totalDebit, (string) $row->debit, 2);
            $totalCredit = bcadd($totalCredit, (string) $row->credit, 2);
            $accounts[] = [
                'account_id' => (string) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'debit' => (string) $row->debit,
                'credit' => (string) $row->credit,
                'net' => $net,
            ];
        }
        $totalNet = bcsub($totalDebit, $totalCredit, 2);

        return [
            'totals' => ['debit' => $totalDebit, 'credit' => $totalCredit, 'net' => $totalNet],
            'accounts' => $accounts,
            'balanced' => bccomp($totalDebit, $totalCredit, 2) === 0,
        ];
    }

    /** @return array<int, array{journals: array{journal_id: string, entry_id: string, posted_at: string, source_type: string, reason: string}, line: array{direction: string, amount: string}}> */
    public function accountDetail(string $accountId, ?string $periodId = null, ?string $organizationId = null): array
    {
        $query = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->where('jl.account_id', $accountId)
            ->select('j.id AS journal_id', 'j.created_at AS posted_at', 'j.source_type', 'j.reason', 'jl.direction', 'jl.amount')
            ->orderBy('j.created_at')
            ->orderBy('jl.id');

        if ($periodId !== null && $periodId !== '') {
            $query->where('j.period_id', $periodId);
        }
        if ($organizationId !== null && $organizationId !== '') {
            $query->where('j.organization_id', $organizationId);
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $rows[] = [
                'journals' => [
                    'journal_id' => (string) $row->journal_id,
                    'entry_id' => (string) $row->journal_id,
                    'posted_at' => (string) $row->posted_at,
                    'source_type' => (string) $row->source_type,
                    'reason' => (string) $row->reason,
                ],
                'line' => ['direction' => (string) $row->direction, 'amount' => (string) $row->amount],
            ];
        }

        return $rows;
    }

    /**
     * Period profit-or-loss. Only revenue and expense accounts participate;
     * revenue is a credit balance, expense a debit balance, and net income is
     * revenue less expense. Scoped to one financial period (and, optionally,
     * one organization) so the statement reflects the period's activity.
     *
     * @return array{total_revenue: string, total_expense: string, net_income: string, accounts: array<int, array{account_id: string, code: string, name: string, type: string, debit: string, credit: string, balance: string}>}
     */
    public function incomeStatement(?string $periodId = null, ?string $organizationId = null): array
    {
        $query = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.type', ['revenue', 'expense'])
            ->selectRaw('a.id AS account_id, a.code AS code, a.name AS name, a.type AS type')
            ->selectRaw('COALESCE(SUM(jl.amount) FILTER (WHERE jl.direction = \'debit\'), 0) AS debit')
            ->selectRaw('COALESCE(SUM(jl.amount) FILTER (WHERE jl.direction = \'credit\'), 0) AS credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->orderBy('a.code');

        if ($periodId !== null && $periodId !== '') {
            $query->where('j.period_id', $periodId);
        }
        if ($organizationId !== null && $organizationId !== '') {
            $query->where('j.organization_id', $organizationId);
        }

        $accounts = [];
        $totalRevenue = '0.00';
        $totalExpense = '0.00';
        foreach ($query->get() as $row) {
            $debit = (string) $row->debit;
            $credit = (string) $row->credit;
            // Revenue is recognized on the credit side; expense on the debit.
            $balance = $row->type === 'revenue' ? bcsub($credit, $debit, 2) : bcsub($debit, $credit, 2);
            if ($row->type === 'revenue') {
                $totalRevenue = bcadd($totalRevenue, $balance, 2);
            } else {
                $totalExpense = bcadd($totalExpense, $balance, 2);
            }
            $accounts[] = [
                'account_id' => (string) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        }
        $netIncome = bcsub($totalRevenue, $totalExpense, 2);

        return [
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => $netIncome,
            'accounts' => $accounts,
        ];
    }

    /**
     * Statement of financial position as of a date. The GL posts every money
     * fact to its source period, so a point-in-time balance sheet accumulates
     * every journal in any period through the target period's end (all periods
     * when no period is given). Assets carry a debit natural balance;
     * liabilities and equity carry a credit natural balance. Because the chart
     * carries no closing entry account, current-period net earnings are shown
     * as part of equity so the identity assets = liabilities + equity holds
     * from the same set of balanced entries.
     *
     * @return array{bottom_line: array{assets: string, liabilities: string, equity: string, net_income: string, balanced: bool}, accounts: array<int, array{account_id: string, code: string, name: string, type: string, balance: string}>}
     */
    public function balanceSheet(?string $periodId = null, ?string $organizationId = null): array
    {
        $scopePeriodIds = null;
        if ($periodId !== null && $periodId !== '') {
            $end = FinancialPeriod::query()->whereKey($periodId)->value('date_to');
            if ($end !== null) {
                $scopePeriodIds = FinancialPeriod::query()->where('date_to', '<=', $end)->pluck('id')->all();
            }
        }
        $entryScope = fn (QueryBuilder $query): QueryBuilder => tap($query)->when($scopePeriodIds !== null, fn ($query) => $query->whereIn('j.period_id', $scopePeriodIds))->when($organizationId !== null && $organizationId !== '', fn ($query) => $query->where('j.organization_id', $organizationId));

        $assets = '0.00';
        $liabilities = '0.00';
        $equity = '0.00';
        $revenue = '0.00';
        $expense = '0.00';
        $accounts = [];

        // Sum by natural balance, keeping revenue and expense out of the
        // reported account list but separating them so current earnings can
        // close into equity.
        $balanceRows = $entryScope(
            DB::table('journal_lines as jl')
                ->join('journals as j', 'j.id', '=', 'jl.journal_id')
                ->join('accounts as a', 'a.id', '=', 'jl.account_id')
                ->selectRaw('a.id AS account_id, a.code AS code, a.name AS name, a.type AS type')
                ->selectRaw('COALESCE(SUM(jl.amount) FILTER (WHERE jl.direction = \'debit\'), 0) AS debit')
                ->selectRaw('COALESCE(SUM(jl.amount) FILTER (WHERE jl.direction = \'credit\'), 0) AS credit')
                ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
                ->orderBy('a.code'),
        )->get();

        foreach ($balanceRows as $row) {
            $debit = (string) $row->debit;
            $credit = (string) $row->credit;
            if ($row->type === 'revenue') {
                $revenue = bcadd($revenue, bcsub($credit, $debit, 2), 2);

                continue;
            }
            if ($row->type === 'expense') {
                $expense = bcadd($expense, bcsub($debit, $credit, 2), 2);

                continue;
            }
            $balance = $row->type === 'asset' ? bcsub($debit, $credit, 2) : bcsub($credit, $debit, 2);
            if ($row->type === 'asset') {
                $assets = bcadd($assets, $balance, 2);
            } elseif ($row->type === 'liability') {
                $liabilities = bcadd($liabilities, $balance, 2);
            } elseif ($row->type === 'equity') {
                $equity = bcadd($equity, $balance, 2);
            }
            $accounts[] = [
                'account_id' => (string) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'balance' => $balance,
            ];
        }
        $netIncome = bcsub($revenue, $expense, 2);
        $effectiveEquity = bcadd($equity, $netIncome, 2);
        $liabilitiesPlusEquity = bcadd($liabilities, $effectiveEquity, 2);

        return [
            'bottom_line' => [
                'assets' => $assets,
                'liabilities' => $liabilities,
                'equity' => $equity,
                'net_income' => $netIncome,
                'balanced' => bccomp($assets, $liabilitiesPlusEquity, 2) === 0,
            ],
            'accounts' => $accounts,
        ];
    }

    /**
     * Completeness is a statement inside one selected organization, not a
     * capability-wide aggregate. Source facts without an organization anchor
     * are deliberately absent rather than being attributed to a tenant by
     * guesswork. Branch-derived facts use the same current effective campus
     * assignment that LedgerAccountResolver uses when it resolves a posting.
     *
     * @return array{total: int, resolved: int, check_total: string, unresolved: array<int, array{source_type: string, source_id: string, amount: string, period_id: string}>}
     */
    public function completeness(?string $periodId, string $organizationId): array
    {
        $organizationId = trim($organizationId);
        if ($organizationId === '') {
            throw new \InvalidArgumentException('ledger completeness requires an organization scope');
        }

        return $this->completenessInScope($periodId, $organizationId);
    }

    /**
     * Closing a period is an internal control, rather than an operator-facing
     * statement. It must inspect every source fact, including a legacy fact
     * with missing organization provenance, so that a malformed record cannot
     * silently let the books close. The API exposes only completeness(), whose
     * organization scope is required and authorization checked at the boundary.
     *
     * @return array{total: int, resolved: int, check_total: string, unresolved: array<int, array{source_type: string, source_id: string, amount: string, period_id: string}>}
     */
    public function completenessForPeriodClose(string $periodId): array
    {
        return $this->completenessInScope($periodId, null);
    }

    /**
     * @return array{total: int, resolved: int, check_total: string, unresolved: array<int, array{source_type: string, source_id: string, amount: string, period_id: string}>}
     */
    private function completenessInScope(?string $periodId, ?string $organizationId): array
    {
        $hasPeriod = $periodId !== null && $periodId !== '';
        $hasOrganizationScope = $organizationId !== null;
        $scoped = static fn (Builder $query, string $column = 'period_id'): Builder => $hasPeriod ? $query->where($column, $periodId) : $query;
        $organizationBranchIds = $hasOrganizationScope ? $this->organizationBranchIds($organizationId) : [];
        $branchScoped = fn (Builder $query, array $branchColumns): Builder => $hasOrganizationScope
            ? $this->whereFirstPresentBranchIn($query, $branchColumns, $organizationBranchIds)
            : $query;
        $organizationObligationIds = fn (): Builder => $branchScoped(
            Obligation::query(),
            ['obligations.current_home_branch_id', 'obligations.originating_branch_id'],
        );
        $fundAllocations = static fn (): Builder => FundAllocation::query()
            ->join('obligation_lines as completeness_allocation_lines', 'completeness_allocation_lines.id', '=', 'fund_allocations.obligation_line_id')
            ->join('obligations as completeness_allocation_obligations', 'completeness_allocation_obligations.id', '=', 'completeness_allocation_lines.obligation_id')
            ->select('fund_allocations.*');
        $organizationFundAllocationIds = fn (): Builder => $branchScoped(
            $fundAllocations(),
            [
                'fund_allocations.current_home_branch_id',
                'fund_allocations.originating_branch_id',
                'completeness_allocation_obligations.current_home_branch_id',
                'completeness_allocation_obligations.originating_branch_id',
            ],
        )->select('fund_allocations.id');

        $providers = [
            'obligation' => fn (): Collection => $scoped($branchScoped(
                Obligation::query(),
                ['obligations.current_home_branch_id', 'obligations.originating_branch_id'],
            ))->pluck('obligations.id'),
            'payment' => fn (): Collection => $scoped($branchScoped(
                Payment::query(),
                ['payments.current_home_branch_id', 'payments.originating_branch_id'],
            ))->pluck('payments.id'),
            // Discounts resolve through their obligation rather than through
            // their own approval actor or any request-time branch hint.
            'discount' => fn (): Collection => $scoped(
                Discount::query()
                    ->where('discounts.lifecycle_state', 'approved')
                    ->whereIn('discounts.obligation_id', $organizationObligationIds()->select('obligations.id')),
                'discounts.period_id',
            )->pluck('discounts.id'),
            'refund' => fn (): Collection => $scoped($branchScoped(
                Refund::query()->where('refunds.lifecycle_state', 'recorded'),
                ['refunds.current_home_branch_id', 'refunds.originating_branch_id'],
            ))->pluck('refunds.id'),
            // A fund allocation prefers its own provenance; only legacy rows
            // without it inherit the linked obligation's organization.
            'fund_allocation' => fn (): Collection => $scoped($branchScoped(
                $fundAllocations(),
                [
                    'fund_allocations.current_home_branch_id',
                    'fund_allocations.originating_branch_id',
                    'completeness_allocation_obligations.current_home_branch_id',
                    'completeness_allocation_obligations.originating_branch_id',
                ],
            ), 'completeness_allocation_obligations.period_id')->pluck('fund_allocations.id'),
            'payroll_liability' => fn (): Collection => $scoped($branchScoped(
                PayrollLiabilityFact::query(),
                ['payroll_liability_facts.originating_branch_id'],
            ), 'payroll_liability_facts.period_id')->pluck('payroll_liability_facts.id'),
            'expense' => fn (): Collection => $scoped($branchScoped(
                Expense::query()->where('expenses.lifecycle_state', 'approved'),
                ['expenses.current_home_branch_id', 'expenses.originating_branch_id'],
            ))->pluck('expenses.id'),
            'correction' => fn (): Collection => $scoped(
                FinancialCorrection::query()
                    ->where('financial_corrections.lifecycle_state', 'recorded')
                    ->where(function (Builder $scope) use ($organizationObligationIds, $organizationFundAllocationIds): void {
                        $scope->where(function (Builder $adjustment) use ($organizationObligationIds): void {
                            $adjustment
                                ->where('financial_corrections.correction_type', FinancialCorrection::TYPE_OBLIGATION_ADJUSTMENT)
                                ->whereIn('financial_corrections.obligation_id', $organizationObligationIds()->select('obligations.id'));
                        })->orWhere(function (Builder $reversal) use ($organizationFundAllocationIds): void {
                            $reversal
                                ->where('financial_corrections.correction_type', FinancialCorrection::TYPE_FUND_ALLOCATION_REVERSAL)
                                ->whereIn('financial_corrections.fund_allocation_id', $organizationFundAllocationIds());
                        });
                    }),
                'financial_corrections.period_id',
            )->pluck('financial_corrections.id'),
            // Employment settlements carry direct Finance organization
            // provenance and are intentionally not re-derived through HR.
            'employment_settlement' => fn (): Collection => $scoped(
                $hasOrganizationScope
                    ? EmploymentSettlement::query()->where('employment_settlements.organization_id', $organizationId)
                    : EmploymentSettlement::query(),
                'employment_settlements.period_id',
            )->pluck('employment_settlements.id'),
        ];

        // Key journalized facts by (source_type, source_id) so a ledger entry is
        // attributed to exactly the fact that produced it even across a shared
        // id space. For an organization statement, a source fact cannot make an
        // entry in another organization's statement appear resolved. Period
        // closing intentionally checks all organizations together.
        $journalizedQuery = Journal::query()->whereNotNull('source_id');
        if ($hasOrganizationScope) {
            $journalizedQuery->where('organization_id', $organizationId);
        }
        $journalized = $journalizedQuery
            ->when($hasPeriod, fn (Builder $query) => $query->where('period_id', $periodId))
            ->get(['source_type', 'source_id'])
            ->mapWithKeys(static fn (Journal $journal): array => [$journal->source_type.'|'.$journal->source_id => true]);

        $unresolved = [];
        $total = 0;
        $resolved = 0;
        $checkTotal = '0.00';
        foreach ($providers as $sourceType => $provider) {
            foreach ($provider() as $sourceId) {
                $sourceId = (string) $sourceId;
                $total++;
                if ($journalized->has($sourceType.'|'.$sourceId)) {
                    $resolved++;

                    continue;
                }
                $amount = $this->sourceAmount($sourceType, $sourceId);
                $checkTotal = bcadd($checkTotal, $amount, 2);
                $unresolved[] = [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'amount' => $amount,
                    'period_id' => $this->sourcePeriod($sourceType, $sourceId),
                ];
            }
        }

        return [
            'total' => $total,
            'resolved' => $resolved,
            'check_total' => $checkTotal,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $branchColumns  Ordered from the source's most
     *                                       preferred provenance to its
     *                                       fallback, exactly like PHP ?? in
     *                                       LedgerAccountResolver.
     * @param  list<string>  $branchIds
     * @return Builder<TModel>
     */
    private function whereFirstPresentBranchIn(Builder $query, array $branchColumns, array $branchIds): Builder
    {
        if ($branchIds === [] || $branchColumns === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($branchColumns, $branchIds): void {
            foreach ($branchColumns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $scope->{$method}(function (Builder $candidate) use ($branchColumns, $branchIds, $column, $index): void {
                    foreach (array_slice($branchColumns, 0, $index) as $higherPrecedenceColumn) {
                        $candidate->whereNull($higherPrecedenceColumn);
                    }
                    $candidate->whereIn($column, $branchIds);
                });
            }
        });
    }

    /**
     * Matches Branch::activeCampusAssignment(): one and only one assignment
     * effective today, with no branch/campus lifecycle filter added. The
     * ledger resolver deliberately uses that current authority relation, so
     * adding a convenient "active branch" predicate here would make the
     * completeness proof disagree with its posting authority.
     *
     * @return list<string>
     */
    private function organizationBranchIds(string $organizationId): array
    {
        $today = app(CalendarAuthority::class)->todayAsString();

        return DB::table('branches as completeness_branch')
            ->join('campus_assignments as completeness_assignment', function ($join) use ($today): void {
                $join->on('completeness_assignment.branch_id', '=', 'completeness_branch.id')
                    ->where('completeness_assignment.effective_from', '<=', $today)
                    ->where(function ($assignment) use ($today): void {
                        $assignment->whereNull('completeness_assignment.effective_to')
                            ->orWhere('completeness_assignment.effective_to', '>', $today);
                    });
            })
            ->join('campuses as completeness_campus', 'completeness_campus.id', '=', 'completeness_assignment.campus_id')
            ->where('completeness_campus.organization_id', $organizationId)
            ->whereNotExists(function (QueryBuilder $competing) use ($today): void {
                $competing->selectRaw('1')
                    ->from('campus_assignments as competing_assignment')
                    ->whereColumn('competing_assignment.branch_id', 'completeness_branch.id')
                    ->whereColumn('competing_assignment.id', '<>', 'completeness_assignment.id')
                    ->where('competing_assignment.effective_from', '<=', $today)
                    ->where(function (QueryBuilder $assignment) use ($today): void {
                        $assignment->whereNull('competing_assignment.effective_to')
                            ->orWhere('competing_assignment.effective_to', '>', $today);
                    });
            })
            ->orderBy('completeness_branch.id')
            ->pluck('completeness_branch.id')
            ->map(static fn (mixed $id): string => trim((string) $id))
            ->filter(static fn (string $id): bool => $id !== '')
            ->values()
            ->all();
    }

    private function sourceAmount(string $sourceType, string $sourceId): string
    {
        return match ($sourceType) {
            'obligation' => (string) (Obligation::query()->whereKey($sourceId)->value('original_amount') ?? '0.00'),
            'payment' => (string) (Payment::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            'discount' => (string) (Discount::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            'refund' => (string) (Refund::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            'fund_allocation' => (string) (FundAllocation::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            'payroll_liability' => $this->absolute((string) (PayrollLiabilityFact::query()->whereKey($sourceId)->value('amount') ?? '0.00')),
            'expense' => (string) (Expense::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            'correction' => (string) (FinancialCorrection::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            'employment_settlement' => (string) (EmploymentSettlement::query()->whereKey($sourceId)->value('amount') ?? '0.00'),
            default => '0.00',
        };
    }

    private function sourcePeriod(string $sourceType, string $sourceId): string
    {
        return match ($sourceType) {
            'obligation' => (string) (Obligation::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'payment' => (string) (Payment::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'discount' => (string) (Discount::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'refund' => (string) (Refund::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'fund_allocation' => (string) (Obligation::query()->join('obligation_lines', 'obligation_lines.obligation_id', '=', 'obligations.id')->where('obligation_lines.id', FundAllocation::query()->whereKey($sourceId)->value('obligation_line_id'))->value('obligations.period_id') ?? ''),
            'payroll_liability' => (string) (PayrollLiabilityFact::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'expense' => (string) (Expense::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'correction' => (string) (FinancialCorrection::query()->whereKey($sourceId)->value('period_id') ?? ''),
            'employment_settlement' => (string) (EmploymentSettlement::query()->whereKey($sourceId)->value('period_id') ?? ''),
            default => '',
        };
    }

    private function absolute(string $amount): string
    {
        return str_starts_with($amount, '-') ? substr($amount, 1) : $amount;
    }
}
