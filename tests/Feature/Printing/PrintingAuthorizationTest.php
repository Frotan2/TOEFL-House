<?php

declare(strict_types=1);

namespace Tests\Feature\Printing;

use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Models\Payment;
use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Modules\Students\Models\Student;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsStudents;
use Tests\TestCase;

/**
 * Printing authorization (WP-ACAD-SCOPE): documents render only when the
 * owning branch is visible to the signed-in actor. Cross-branch production
 * attempts are refused with 403 and denial-audited; every production is
 * audit-logged. Records outside the actor's branch — including bootstrap
 * provenance — render for an organization-wide holder but never for a bare
 * session.
 */
final class PrintingAuthorizationTest extends TestCase
{
    use BuildsStudents;

    private string $branchA;

    private string $branchB;

    private string $studentA;

    private string $studentB;

    private string $studentBootstrap;

    private string $payA;

    private string $payB;

    private string $payBootstrap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = $this->newBranch('Print Branch A');
        $this->branchB = $this->newBranch('Print Branch B');

        $this->studentA = $this->makeStudent()['student']->id;
        $this->studentB = $this->makeStudent()['student']->id;
        $this->studentBootstrap = $this->makeStudent()['student']->id;
        // Provenance seeding: these students belong to their branches; the
        // third keeps the bootstrap branch (the only provenance the intake
        // pipeline can produce — branchless people no longer exist).
        $this->transferStudentHome($this->studentA, $this->branchA, 'hb5');
        $this->transferStudentHome($this->studentB, $this->branchB, 'hb6');

        $period = FinancialPeriod::query()->create([
            'id' => RandomIdentifier::new(),
            'period_key' => '2026-10',
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
            'lifecycle_state' => 'open',
        ]);

        // Payments carry their originating branch as an immutable fact; the
        // receipt gate reads that branch (never the mutable student home).
        $this->payA = $this->newPayment($period->id, $this->studentA, 'PAY-PA-001', $this->branchA);
        $this->payB = $this->newPayment($period->id, $this->studentB, 'PAY-PB-001', $this->branchB);
        $this->payBootstrap = $this->newPayment($period->id, $this->studentBootstrap, 'PAY-PB-002', $this->bootstrapBranchId());

        $this->makeLogin('officer.a', 'prt-officer-a', ['academic.enroll'], $this->branchA);
        $this->makeLogin('officer.org', 'prt-officer-org', ['academic.enroll'], null);
        $this->makeLogin('officer.bare', 'prt-officer-bare', [], null);
    }

    private function newBranch(string $name): string
    {
        $id = Branch::query()->create([
            'id' => RandomIdentifier::new(),
            'name' => $name.' '.substr(md5(RandomIdentifier::new()), 0, 8),
            'lifecycle_state' => 'active',
        ])->id;
        $this->attachBranchToBootstrapOrganization($id);

        return $id;
    }

    private function newPayment(string $periodId, string $studentId, string $payerRef, string $branchId): string
    {
        return Payment::query()->create([
            'id' => RandomIdentifier::new(),
            'period_id' => $periodId,
            'student_id' => $studentId,
            // The provenance guard requires an active originating branch on
            // every new payment (here the student's home at record time).
            'originating_branch_id' => $branchId,
            'current_home_branch_id' => $branchId,
            'amount' => '250.00',
            'method' => 'cash',
            'payer_ref' => $payerRef,
            'received_on' => '2026-10-10',
            'recorded_by' => 'prt-fixture-1',
        ])->id;
    }

    /** @param list<string> $capabilities */
    private function makeLogin(string $username, string $personId, array $capabilities, ?string $branchId): void
    {
        if ($branchId === null) {
            $this->personWithAuthority($personId, $capabilities);
        } else {
            $this->personWithAuthority($personId, []);
            $this->grantScopeAuthority($personId, $capabilities, 'branch', $branchId);
        }
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => Hash::make('prt-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    private function signIn(string $username): void
    {
        $this->post('/login', ['username' => $username, 'password' => 'prt-password-1'])->assertRedirect('/');
    }

    public function test_cross_branch_receipt_production_is_refused_and_denial_audited(): void
    {
        $this->signIn('officer.a');

        // Direct-URL bypass attempt: guessing the branch-B receipt URL.
        // Over the web the denial returns the employee with a flash error;
        // JSON clients get the 403 — both refuse, both denial-audit.
        $this->get('/print/receipt/'.$this->payB)
            ->assertRedirect()
            ->assertSessionHas('error_code', 'print.denied');
        $this->getJson('/print/receipt/'.$this->payB)
            ->assertForbidden()
            ->assertJsonPath('error', 'print.denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'print.receipt.denied',
            'actor_id' => 'prt-officer-a',
        ]);

        // The ID-card gate derives the same way from the student row.
        $this->get('/print/id-card/'.$this->studentB)
            ->assertRedirect()
            ->assertSessionHas('error_code', 'print.denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'print.id_card.denied',
            'actor_id' => 'prt-officer-a',
        ]);
    }

    public function test_home_branch_documents_render_and_production_is_audited(): void
    {
        $this->signIn('officer.a');

        $this->get('/print/receipt/'.$this->payA)
            ->assertOk()
            ->assertSee('PAY-PA-001');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'print.receipt',
            'actor_id' => 'prt-officer-a',
        ]);

        $this->get('/print/id-card/'.$this->studentA)->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'print.id_card',
            'actor_id' => 'prt-officer-a',
        ]);
    }

    public function test_out_of_scope_provenance_documents_render_for_org_wide_holders_only(): void
    {
        // The bootstrap-provenance receipt is OUTSIDE the branch-A officer's
        // visible set: branch grants never leak into other branches.
        $this->signIn('officer.a');
        $this->getJson('/print/receipt/'.$this->payBootstrap)->assertForbidden();
        $this->getJson('/print/id-card/'.$this->studentBootstrap)->assertForbidden();

        // An organization-wide holder sees every branch under the
        // institution: ancestor scope covers descendants.
        $this->post('/logout')->assertRedirect('/login');
        $this->signIn('officer.org');
        $this->get('/print/receipt/'.$this->payBootstrap)->assertOk()->assertSee('PAY-PB-002');
        $this->get('/print/receipt/'.$this->payA)->assertOk()->assertSee('PAY-PA-001');
        $this->get('/print/id-card/'.$this->studentBootstrap)->assertOk();

        // A bare session with no authority grant renders nothing.
        $this->post('/logout')->assertRedirect('/login');
        $this->signIn('officer.bare');
        $this->getJson('/print/receipt/'.$this->payBootstrap)->assertForbidden();
        $this->getJson('/print/id-card/'.$this->studentBootstrap)->assertForbidden();
    }
}
