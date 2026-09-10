<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Finance\Commands\PostObligation;
use App\Modules\Finance\Domain\LedgerAccountResolver;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FinancialCorrection;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\FundAllocation;
use App\Modules\Finance\Models\FundingSource;
use App\Modules\Finance\Models\Obligation;
use App\Modules\Finance\Models\ObligationLine;
use App\Modules\Finance\Queries\GeneralLedgerQuery;
use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\CampusAssignment;
use App\Modules\Organization\Models\Organization;
use App\Modules\Students\Commands\TransferStudentHomeBranch;
use App\Modules\Students\Models\Student;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsStudents;
use Tests\TestCase;

/**
 * Two-organization ledger confinement. These scenarios intentionally combine
 * real source-posting commands with a bounded legacy fixture: a statement may
 * never inherit another organization's facts merely because their IDs or
 * linked obligation records happen to be reachable.
 */
final class FinanceLedgerOrganizationScopeTest extends TestCase
{
    use BuildsStudents;

    private string $organizationA;

    private string $organizationB;

    private string $branchA;

    private string $branchB;

    private string $periodId;

    private string $writerId = 'finance-ledger-scope-writer';

    private string $studentAId;

    private string $studentBId;

    private string $journalAId;

    private string $journalBId;

    private string $unresolvedBId;

    private string $receivableAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureBootstrapAuthority();

        $this->organizationA = $this->bootstrapOrganizationId;
        $this->branchA = $this->bootstrapBranchId();
        [$this->organizationB, $this->branchB] = $this->createOrganizationBranch('Ledger Scope B');

        $this->studentAId = $this->makeStudent([
            'initiator' => 'finance-ledger-a-init',
            'reviewer' => 'finance-ledger-a-review',
            'approver' => 'finance-ledger-a-approve',
            'applicant' => RandomIdentifier::new(),
        ])['student']->id;
        $this->studentBId = $this->makeStudent([
            'initiator' => 'finance-ledger-b-init',
            'reviewer' => 'finance-ledger-b-review',
            'approver' => 'finance-ledger-b-approve',
            'applicant' => RandomIdentifier::new(),
        ])['student']->id;
        $this->moveStudentToOrganizationB();

        $writer = $this->writer();
        $period = app(MaintainFinancialPeriod::class)->open(
            $writer,
            'ledger-scope-'.substr(str_replace('-', '', RandomIdentifier::new()), 0, 12),
            '2026-09-01',
            '2026-09-30',
            'ledger-scope-period-open',
        );
        $this->periodId = $period['period_id'];

        $postedA = app(PostObligation::class)->post(
            $writer,
            FinancialPeriod::query()->findOrFail($this->periodId),
            $this->studentAId,
            'tuition',
            'Organization A tuition',
            [['category' => 'tuition', 'amount' => '101.00', 'source_ref' => 'ledger-scope/a']],
            'ledger-scope-obligation-a',
        );
        $postedB = app(PostObligation::class)->post(
            $writer,
            FinancialPeriod::query()->findOrFail($this->periodId),
            $this->studentBId,
            'tuition',
            'Organization B tuition',
            [['category' => 'tuition', 'amount' => '202.00', 'source_ref' => 'ledger-scope/b']],
            'ledger-scope-obligation-b',
        );
        $this->journalAId = (string) $postedA['journal_id'];
        $this->journalBId = (string) $postedB['journal_id'];

        // A deliberately unjournalized, provenance-complete legacy source
        // verifies that completeness scopes source facts as well as journals.
        $this->unresolvedBId = RandomIdentifier::new();
        Obligation::query()->create([
            'id' => $this->unresolvedBId,
            'period_id' => $this->periodId,
            'student_id' => $this->studentBId,
            'source' => 'legacy-import',
            'original_amount' => '33.00',
            'reason' => 'Legacy fact retained for ledger completeness proof',
            'posted_by' => $this->writerId,
            'originating_branch_id' => $this->branchB,
            'current_home_branch_id' => $this->branchB,
        ]);
        ObligationLine::query()->create([
            'id' => RandomIdentifier::new(),
            'obligation_id' => $this->unresolvedBId,
            'category' => 'tuition',
            'amount' => '33.00',
            'source_ref' => 'ledger-scope/legacy-b',
        ]);

        /** @var Account $receivable */
        $receivable = Account::query()->where('code', LedgerAccountResolver::ACCOUNT_RECEIVABLE)->firstOrFail();
        $this->receivableAccountId = (string) $receivable->id;

        $this->createAOnlyReader();
    }

    public function test_bootstrap_and_every_ledger_api_require_an_authorized_organization_and_confine_journals(): void
    {
        $this->signInReader();

        $bootstrap = $this->getJson('/api/v1/finance/ledger/bootstrap')->assertOk()->json();
        $this->assertSame([[$this->organizationA, 'Authority Bootstrap']], array_map(
            static fn (array $organization): array => [(string) $organization['id'], (string) $organization['name']],
            $bootstrap['organizations'],
        ));
        $this->assertSame($this->organizationA, $bootstrap['default_organization_id']);

        $missingScopeUrls = [
            '/api/v1/finance/ledger/trial-balance',
            '/api/v1/finance/ledger/accounts/'.$this->receivableAccountId,
            '/api/v1/finance/ledger/completeness',
            '/api/v1/finance/ledger/income-statement?period_id='.$this->periodId,
            '/api/v1/finance/ledger/balance-sheet',
        ];
        foreach ($missingScopeUrls as $url) {
            $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors(['organization_id']);
        }

        $trial = $this->getJson($this->ledgerUrl('trial-balance', $this->organizationA))->assertOk()->json();
        $this->assertSame('101.00', $trial['totals']['debit']);
        $this->assertSame('101.00', $trial['totals']['credit']);
        $this->assertTrue($trial['balanced']);

        $detail = $this->getJson($this->ledgerUrl('accounts/'.$this->receivableAccountId, $this->organizationA))->assertOk()->json();
        $journalIds = array_map(static fn (array $entry): string => (string) $entry['journals']['journal_id'], $detail['entries']);
        $this->assertSame([$this->journalAId], $journalIds);
        $this->assertNotContains($this->journalBId, $journalIds);

        $income = $this->getJson($this->ledgerUrl('income-statement', $this->organizationA, true))->assertOk()->json();
        $this->assertSame('101.00', $income['total_revenue']);
        $this->assertSame('0.00', $income['total_expense']);
        $this->assertSame('101.00', $income['net_income']);

        $balance = $this->getJson($this->ledgerUrl('balance-sheet', $this->organizationA))->assertOk()->json();
        $this->assertSame('101.00', $balance['bottom_line']['assets']);
        $this->assertSame('101.00', $balance['bottom_line']['net_income']);
        $this->assertTrue($balance['bottom_line']['balanced']);

        $completeness = $this->getJson($this->ledgerUrl('completeness', $this->organizationA))->assertOk()->json();
        $this->assertSame(1, $completeness['total']);
        $this->assertSame(1, $completeness['resolved']);
        $this->assertSame('0.00', $completeness['check_total']);
        $this->assertSame([], $completeness['unresolved']);

        foreach ([
            $this->ledgerUrl('trial-balance', $this->organizationB),
            $this->ledgerUrl('accounts/'.$this->receivableAccountId, $this->organizationB),
            $this->ledgerUrl('completeness', $this->organizationB),
            $this->ledgerUrl('income-statement', $this->organizationB, true),
            $this->ledgerUrl('balance-sheet', $this->organizationB),
        ] as $url) {
            $this->getJson($url)->assertForbidden()->assertJsonPath('error', 'api.organization_read_denied');
        }
    }

    public function test_completeness_keeps_unresolved_facts_in_their_organization_and_period_closure_checks_every_organization(): void
    {
        $ledger = app(GeneralLedgerQuery::class);

        $organizationA = $ledger->completeness($this->periodId, $this->organizationA);
        $this->assertSame(1, $organizationA['total']);
        $this->assertSame(1, $organizationA['resolved']);
        $this->assertSame([], $organizationA['unresolved']);

        $organizationB = $ledger->completeness($this->periodId, $this->organizationB);
        $this->assertSame(2, $organizationB['total']);
        $this->assertSame(1, $organizationB['resolved']);
        $this->assertSame('33.00', $organizationB['check_total']);
        $this->assertSame([['obligation', $this->unresolvedBId, '33.00']], array_map(
            static fn (array $fact): array => [$fact['source_type'], $fact['source_id'], $fact['amount']],
            $organizationB['unresolved'],
        ));

        $periodClose = $ledger->completenessForPeriodClose($this->periodId);
        $this->assertSame(3, $periodClose['total']);
        $this->assertSame(2, $periodClose['resolved']);
        $this->assertSame([['obligation', $this->unresolvedBId]], array_map(
            static fn (array $fact): array => [$fact['source_type'], $fact['source_id']],
            $periodClose['unresolved'],
        ));

        try {
            app(MaintainFinancialPeriod::class)->close(
                $this->writer(),
                FinancialPeriod::query()->findOrFail($this->periodId),
                'ledger-scope-period-close',
            );
            $this->fail('an unresolved source in another organization must block period closure');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('finance.period_incomplete_ledger', $rejection->errorCode());
        }
    }

    public function test_correction_reversal_prefers_its_fund_allocation_provenance_over_the_linked_obligation(): void
    {
        $legacyObligationId = RandomIdentifier::new();
        $legacyLineId = RandomIdentifier::new();
        Obligation::query()->create([
            'id' => $legacyObligationId,
            'period_id' => $this->periodId,
            'student_id' => $this->studentAId,
            'source' => 'legacy-import',
            'original_amount' => '50.00',
            'reason' => 'Legacy allocation scope fixture',
            'posted_by' => $this->writerId,
            'originating_branch_id' => $this->branchA,
            'current_home_branch_id' => $this->branchA,
        ]);
        ObligationLine::query()->create([
            'id' => $legacyLineId,
            'obligation_id' => $legacyObligationId,
            'category' => 'tuition',
            'amount' => '50.00',
            'source_ref' => 'ledger-scope/correction-line',
        ]);
        $fund = FundingSource::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $this->organizationA,
            'name' => 'Legacy allocation scope fund',
            'agreement_ref' => 'ledger-scope/correction-fund',
            'committed_amount' => '50.00',
            'restricted_category' => null,
            'restriction_note' => null,
            'established_by' => $this->writerId,
        ]);
        $allocationId = RandomIdentifier::new();
        $correctionId = RandomIdentifier::new();

        // Current writes are rejected by the organization guards when an
        // allocation and obligation disagree. Replica mode is confined to this
        // test transaction to model pre-convergence history, which is exactly
        // the history a read model must classify without tenant leakage.
        DB::statement("SET LOCAL session_replication_role = 'replica'");
        try {
            FundAllocation::query()->create([
                'id' => $allocationId,
                'fund_id' => $fund->id,
                'obligation_line_id' => $legacyLineId,
                'amount' => '20.00',
                'reason' => 'Pre-convergence allocation provenance',
                'allocated_by' => $this->writerId,
                'originating_branch_id' => $this->branchB,
                'current_home_branch_id' => $this->branchB,
            ]);
            FinancialCorrection::query()->create([
                'id' => $correctionId,
                'period_id' => $this->periodId,
                'correction_type' => FinancialCorrection::TYPE_FUND_ALLOCATION_REVERSAL,
                'obligation_id' => null,
                'payment_allocation_id' => null,
                'fund_allocation_id' => $allocationId,
                'amount' => '20.00',
                'direction' => FinancialCorrection::DIRECTION_DECREASE,
                'reason' => 'Pre-convergence reversal provenance',
                'lifecycle_state' => FinancialCorrection::STATE_RECORDED,
                'requested_by' => $this->writerId,
                'approved_by' => 'finance-ledger-scope-approver',
                'approved_at' => now(),
            ]);
        } finally {
            DB::statement("SET LOCAL session_replication_role = 'origin'");
        }

        $resolved = app(LedgerAccountResolver::class)->resolve('correction', $correctionId);
        $this->assertSame($this->organizationB, $resolved['organization_id']);

        $ledger = app(GeneralLedgerQuery::class);
        $organizationA = $ledger->completeness($this->periodId, $this->organizationA);
        $organizationAFacts = array_map(static fn (array $fact): string => $fact['source_type'].'|'.$fact['source_id'], $organizationA['unresolved']);
        $this->assertNotContains('fund_allocation|'.$allocationId, $organizationAFacts);
        $this->assertNotContains('correction|'.$correctionId, $organizationAFacts);

        $organizationB = $ledger->completeness($this->periodId, $this->organizationB);
        $organizationBFacts = array_map(static fn (array $fact): string => $fact['source_type'].'|'.$fact['source_id'], $organizationB['unresolved']);
        $this->assertContains('fund_allocation|'.$allocationId, $organizationBFacts);
        $this->assertContains('correction|'.$correctionId, $organizationBFacts);
    }

    /** @return array{0: string, 1: string} */
    private function createOrganizationBranch(string $name): array
    {
        $organization = Organization::query()->create([
            'id' => RandomIdentifier::new(),
            'name' => $name,
            'lifecycle_state' => 'active',
        ]);
        $campus = Campus::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $organization->id,
            'name' => $name.' Campus',
            'lifecycle_state' => 'active',
        ]);
        $branch = Branch::query()->create([
            'id' => RandomIdentifier::new(),
            'name' => $name.' Branch',
            'lifecycle_state' => 'active',
        ]);
        CampusAssignment::query()->create([
            'id' => RandomIdentifier::new(),
            'branch_id' => $branch->id,
            'campus_id' => $campus->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'transfer_correlation_id' => RandomIdentifier::new(),
        ]);

        return [(string) $organization->id, (string) $branch->id];
    }

    private function moveStudentToOrganizationB(): void
    {
        $actorId = 'finance-ledger-scope-transfer';
        $this->personWithAuthority($actorId, []);
        $this->grantScopeAuthority($actorId, ['students.transfer'], 'organization', $this->organizationA);
        $this->grantScopeAuthority($actorId, ['students.transfer'], 'organization', $this->organizationB);
        app(TransferStudentHomeBranch::class)->transfer(
            new Actor($actorId, 'Ledger Scope Transfer Officer'),
            Student::query()->findOrFail($this->studentBId),
            $this->branchB,
            'Move the organization B ledger fixture through the production transfer flow',
            'ledger-scope-student-b-transfer',
        );
    }

    private function writer(): Actor
    {
        $this->grantedActor($this->writerId, ['finance.period', 'finance.obligation']);
        $this->grantScopeAuthority($this->writerId, ['finance.obligation'], 'organization', $this->organizationB);

        return new Actor($this->writerId, 'Ledger Scope Finance Writer');
    }

    private function createAOnlyReader(): void
    {
        $readerId = 'finance-ledger-scope-reader';
        $this->personWithAuthority($readerId, []);
        $this->grantScopeAuthority($readerId, ['finance.journal'], 'organization', $this->organizationA);
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $readerId,
            'username' => 'finance-ledger-scope-reader',
            'password_hash' => Hash::make('finance-ledger-scope-password'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    private function signInReader(): void
    {
        $this->post('/login', [
            'username' => 'finance-ledger-scope-reader',
            'password' => 'finance-ledger-scope-password',
        ])->assertRedirect('/');
    }

    private function ledgerUrl(string $path, string $organizationId, bool $includePeriod = false): string
    {
        $query = ['organization_id' => $organizationId];
        if ($includePeriod) {
            $query['period_id'] = $this->periodId;
        }

        return '/api/v1/finance/ledger/'.$path.'?'.http_build_query($query);
    }
}
