<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Organization\Models\Branch;
use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssuance;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Regression coverage for issuance closure recovery: borrower provenance is
 * verified when the issuance fact is created; closing an issuance afterwards
 * must stay possible even when the borrower's home branch is later closed.
 * This mirrors the custody doctrine the database enforces in 000197 ("a later
 * person home-branch transfer must not make it impossible to close an
 * already-recorded custody history").
 */
final class IssuanceCloseRecoveryTest extends TestCase
{
    use BuildsActors;

    private string $branchA;

    private string $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branchA = RandomIdentifier::new();
        $this->branchB = RandomIdentifier::new();
        foreach ([$this->branchA => 'Recovery Copy Branch', $this->branchB => 'Recovery Borrower Branch'] as $id => $name) {
            Branch::query()->create(['id' => $id, 'name' => $name, 'lifecycle_state' => 'active']);
            $this->attachBranchToBootstrapOrganization($id);
        }
    }

    /** @return array{0: Actor, 1: BookIssuance, 2: BookCopy} */
    private function openIssuance(string $managerId): array
    {
        $manager = $this->grantedActor($managerId, ['resources.books']);
        $this->personWithAuthority('recovery-borrower-1', [], $this->branchB);
        $copyId = app(CirculateBooks::class)->addCopy(
            $manager, 'REC-'.RandomIdentifier::new(), 'Recovery book', '2026-01-10', $this->branchA, 'rec-copy-'.$managerId
        )['copy_id'];
        $issuanceId = app(CirculateBooks::class)->issue(
            $manager, BookCopy::query()->findOrFail($copyId), 'recovery-borrower-1', '2026-02-01', '2026-03-01', 'rec-issue-'.$managerId
        )['issuance_id'];

        return [$manager, BookIssuance::query()->findOrFail($issuanceId), BookCopy::query()->findOrFail($copyId)];
    }

    public function test_return_completes_after_the_borrower_home_branch_is_closed(): void
    {
        [$manager, $issuance] = $this->openIssuance('rec-mgr-1');
        Branch::query()->whereKey($this->branchB)->update(['lifecycle_state' => 'closed']);

        $result = app(CirculateBooks::class)->returned($manager, $issuance, '2026-03-05', 'rec-return-1');

        $this->assertSame('returned', $result['lifecycle_state']);
        $this->assertDatabaseHas('book_issuances', [
            'id' => $issuance->id, 'lifecycle_state' => 'returned', 'returned_on' => '2026-03-05',
        ]);

        $event = AuditEvent::query()
            ->where('operation', 'resources.books.close')
            ->where('target_id', $issuance->id)
            ->firstOrFail();
        $this->assertSame('recovery-borrower-1', $event->after_state['borrower_person_id']);
        $this->assertSame($this->branchA, $event->after_state['branch_id']);
    }

    public function test_loss_can_be_recorded_after_the_borrower_home_branch_is_closed(): void
    {
        [$manager, $issuance] = $this->openIssuance('rec-mgr-2');
        Branch::query()->whereKey($this->branchB)->update(['lifecycle_state' => 'closed']);

        $result = app(CirculateBooks::class)->reportLoss($manager, $issuance, 'police report 99', 'rec-loss-2');

        $this->assertSame('lost', $result['lifecycle_state']);
        $this->assertDatabaseHas('book_issuances', [
            'id' => $issuance->id, 'lifecycle_state' => 'lost', 'loss_evidence' => 'police report 99',
        ]);
    }

    public function test_copy_returns_to_circulation_after_a_post_closure_return(): void
    {
        [$manager, $issuance, $copy] = $this->openIssuance('rec-mgr-3');
        Branch::query()->whereKey($this->branchB)->update(['lifecycle_state' => 'closed']);
        app(CirculateBooks::class)->returned($manager, $issuance, '2026-03-05', 'rec-return-3');

        $this->personWithAuthority('recovery-borrower-2', [], $this->branchA);
        $second = app(CirculateBooks::class)->issue($manager, $copy, 'recovery-borrower-2', '2026-03-06', '2026-04-06', 'rec-reissue-3');

        $this->assertSame('issued', BookIssuance::query()->findOrFail($second['issuance_id'])->lifecycle_state);
        $this->assertSame(1, BookIssuance::query()->where('copy_id', $copy->id)->where('lifecycle_state', 'issued')->count());
        $this->assertSame(2, BookIssuance::query()->where('copy_id', $copy->id)->count(), 'issuance history is retained');
    }

    public function test_issue_still_requires_resolvable_borrower_provenance(): void
    {
        $manager = $this->grantedActor('rec-mgr-4', ['resources.books']);
        $this->personWithAuthority('recovery-borrower-4', [], $this->branchB);
        $copyId = app(CirculateBooks::class)->addCopy($manager, 'REC-4', 'Recovery book 4', '2026-01-10', $this->branchA, 'rec-copy-4')['copy_id'];
        Branch::query()->whereKey($this->branchB)->update(['lifecycle_state' => 'closed']);

        try {
            app(CirculateBooks::class)->issue($manager, BookCopy::query()->findOrFail($copyId), 'recovery-borrower-4', '2026-02-01', '2026-03-01', 'rec-issue-4');
            $this->fail('issuance creation must still require a resolvable borrower home branch');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('identity.person_provenance_required', $rejection->errorCode());
        }
    }

    public function test_close_still_denies_actors_without_the_books_capability_and_audits_the_denial(): void
    {
        [, $issuance] = $this->openIssuance('rec-mgr-5');
        $intruder = $this->grantedActor('rec-intruder-5', ['resources.asset']);

        try {
            app(CirculateBooks::class)->returned($intruder, $issuance, '2026-03-05', 'rec-return-5');
            $this->fail('an actor without resources.books must be denied at closure');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.books_denied', $denial->errorCode());
        }

        $this->assertDatabaseHas('audit_events', [
            'operation' => 'resources.books.return.denied',
            'actor_id' => 'rec-intruder-5',
            'target_type' => 'book_issuance',
            'target_id' => $issuance->id,
        ]);
        $this->assertSame('issued', BookIssuance::query()->findOrFail($issuance->id)->lifecycle_state);
    }

    public function test_closed_issuances_remain_terminal(): void
    {
        [$manager, $issuance] = $this->openIssuance('rec-mgr-6');
        app(CirculateBooks::class)->returned($manager, $issuance, '2026-03-05', 'rec-return-6');

        try {
            app(CirculateBooks::class)->returned($manager, $issuance, '2026-03-06', 'rec-return-6b');
            $this->fail('a returned issuance must not close twice');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.issuance_transition_forbidden', $rejection->errorCode());
        }

        try {
            app(CirculateBooks::class)->reportLoss($manager, $issuance, 'late evidence', 'rec-loss-6');
            $this->fail('a returned issuance must not become lost');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.issuance_transition_forbidden', $rejection->errorCode());
        }

        $this->assertDatabaseHas('book_issuances', [
            'id' => $issuance->id, 'lifecycle_state' => 'returned', 'returned_on' => '2026-03-05',
        ]);
    }
}
