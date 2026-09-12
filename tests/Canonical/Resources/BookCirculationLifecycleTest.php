<?php

declare(strict_types=1);

namespace Tests\Canonical\Resources;

use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\CampusAssignment;
use App\Modules\Organization\Models\Organization;
use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssuance;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical book circulation: immutable catalog copies, one open issuance
 * per copy, terminal returned/lost history, borrower-organization binding at
 * creation time, and closure that survives later borrower topology changes.
 *
 * Every fixture is produced by the production command; every assertion is
 * against persisted state (rows, audit events, transactional outbox).
 */
final class BookCirculationLifecycleTest extends CanonicalTestCase
{
    private Actor $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->librarian = $this->actorWith('canon-librarian', ['resources.books']);
    }

    private function addCopy(string $key, string $code = 'CANON-BOOK'): BookCopy
    {
        $copyId = app(CirculateBooks::class)->addCopy(
            $this->librarian, $code.RandomIdentifier::new(), 'Canonical volume', '2026-01-10', $this->sharedBranchId(), $key
        )['copy_id'];

        return BookCopy::query()->findOrFail($copyId);
    }

    public function test_a_copy_circulates_through_issue_return_and_reissue_with_persisted_history(): void
    {
        $copy = $this->addCopy('canon-book-add-1');
        $firstBorrower = $this->personWithAuthority('canon-borrower-1', [], $this->sharedBranchId());
        $secondBorrower = $this->personWithAuthority('canon-borrower-2', [], $this->sharedBranchId());

        $issued = app(CirculateBooks::class)->issue($this->librarian, $copy, $firstBorrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-1');
        $this->assertDatabaseHas('book_issuances', [
            'id' => $issued['issuance_id'], 'copy_id' => $copy->id, 'borrower_person_id' => $firstBorrower->id,
            'issued_on' => '2026-02-01', 'due_on' => '2026-03-01', 'lifecycle_state' => 'issued', 'issued_by' => 'canon-librarian',
        ]);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $issued['correlation_id']]);

        $returned = app(CirculateBooks::class)->returned($this->librarian, BookIssuance::query()->findOrFail($issued['issuance_id']), '2026-02-20', 'canon-book-return-1');
        $this->assertSame('returned', $returned['lifecycle_state']);
        $this->assertDatabaseHas('book_issuances', ['id' => $issued['issuance_id'], 'lifecycle_state' => 'returned', 'returned_on' => '2026-02-20']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $returned['correlation_id']]);

        $reissued = app(CirculateBooks::class)->issue($this->librarian, $copy, $secondBorrower->id, '2026-02-25', '2026-03-25', 'canon-book-issue-2');
        $this->assertSame(2, BookIssuance::query()->where('copy_id', $copy->id)->count(), 'circulation history is retained');
        $this->assertSame(1, BookIssuance::query()->where('copy_id', $copy->id)->where('lifecycle_state', 'issued')->count());
        $this->assertDatabaseHas('book_issuances', ['id' => $reissued['issuance_id'], 'lifecycle_state' => 'issued']);
    }

    public function test_the_database_enforces_one_open_issuance_per_copy(): void
    {
        $copy = $this->addCopy('canon-book-add-2');
        $borrower = $this->personWithAuthority('canon-borrower-3', [], $this->sharedBranchId());
        app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-3');

        $this->expectException(QueryException::class);
        DB::table('book_issuances')->insert([
            'id' => RandomIdentifier::new(),
            'copy_id' => $copy->id,
            'borrower_person_id' => $borrower->id,
            'issued_on' => '2026-02-02',
            'due_on' => '2026-03-02',
            'lifecycle_state' => 'issued',
            'issued_by' => 'canon-librarian',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_lost_copy_leaves_circulation_permanently_and_loss_requires_evidence(): void
    {
        $copy = $this->addCopy('canon-book-add-3');
        $borrower = $this->personWithAuthority('canon-borrower-4', [], $this->sharedBranchId());
        $issued = app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-4');
        $issuance = BookIssuance::query()->findOrFail($issued['issuance_id']);

        try {
            app(CirculateBooks::class)->reportLoss($this->librarian, $issuance, '', 'canon-book-loss-empty');
            $this->fail('a loss report requires evidence');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.loss_evidence', $rejection->errorCode());
        }

        $lost = app(CirculateBooks::class)->reportLoss($this->librarian, $issuance, 'police report 42', 'canon-book-loss-1');
        $this->assertSame('lost', $lost['lifecycle_state']);
        $this->assertDatabaseHas('book_issuances', ['id' => $issuance->id, 'lifecycle_state' => 'lost', 'loss_evidence' => 'police report 42']);

        $secondBorrower = $this->personWithAuthority('canon-borrower-5', [], $this->sharedBranchId());
        try {
            app(CirculateBooks::class)->issue($this->librarian, $copy, $secondBorrower->id, '2026-03-02', '2026-04-02', 'canon-book-issue-5');
            $this->fail('a lost copy never re-enters circulation');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.copy_lost', $rejection->errorCode());
        }
    }

    public function test_issuance_history_is_immutable_at_the_database_boundary(): void
    {
        $copy = $this->addCopy('canon-book-add-4');
        $borrower = $this->personWithAuthority('canon-borrower-6', [], $this->sharedBranchId());
        $issued = app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-6');
        app(CirculateBooks::class)->returned($this->librarian, BookIssuance::query()->findOrFail($issued['issuance_id']), '2026-02-20', 'canon-book-return-4');

        try {
            DB::table('book_issuances')->where('id', $issued['issuance_id'])->update(['issued_on' => '2026-01-01']);
            $this->fail('terminal issuance history must be immutable');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('retained history', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        DB::table('book_issuances')->where('id', $issued['issuance_id'])->delete();
    }

    public function test_closure_survives_a_later_borrower_branch_closure(): void
    {
        $copy = $this->addCopy('canon-book-add-5');
        $borrowerBranch = RandomIdentifier::new();
        Branch::query()->create(['id' => $borrowerBranch, 'name' => 'Canonical borrower branch', 'lifecycle_state' => 'active']);
        $this->attachBranchToBootstrapOrganization($borrowerBranch);
        $borrower = $this->personWithAuthority('canon-borrower-7', [], $borrowerBranch);

        $issued = app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-7');
        $issuance = BookIssuance::query()->findOrFail($issued['issuance_id']);

        Branch::query()->whereKey($borrowerBranch)->update(['lifecycle_state' => 'closed']);

        $returned = app(CirculateBooks::class)->returned($this->librarian, $issuance, '2026-02-28', 'canon-book-return-5');
        $this->assertSame('returned', $returned['lifecycle_state']);
        $this->assertDatabaseHas('book_issuances', ['id' => $issuance->id, 'lifecycle_state' => 'returned']);
    }

    public function test_issuance_creation_still_requires_resolvable_borrower_provenance(): void
    {
        $copy = $this->addCopy('canon-book-add-6');
        $borrowerBranch = RandomIdentifier::new();
        Branch::query()->create(['id' => $borrowerBranch, 'name' => 'Canonical closing branch', 'lifecycle_state' => 'active']);
        $this->attachBranchToBootstrapOrganization($borrowerBranch);
        $borrower = $this->personWithAuthority('canon-borrower-8', [], $borrowerBranch);
        Branch::query()->whereKey($borrowerBranch)->update(['lifecycle_state' => 'closed']);

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('person-linked operations require an active home branch');
        app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-8');
    }

    public function test_a_borrower_from_another_organization_is_refused_at_creation(): void
    {
        $copy = $this->addCopy('canon-book-add-7');

        $otherOrganization = RandomIdentifier::new();
        $otherCampus = RandomIdentifier::new();
        $otherBranch = RandomIdentifier::new();
        Organization::query()->create(['id' => $otherOrganization, 'name' => 'Canonical other organization', 'lifecycle_state' => 'active']);
        Campus::query()->create(['id' => $otherCampus, 'organization_id' => $otherOrganization, 'name' => 'Canonical other campus', 'lifecycle_state' => 'active']);
        Branch::query()->create(['id' => $otherBranch, 'name' => 'Canonical other branch', 'lifecycle_state' => 'active']);
        CampusAssignment::query()->create([
            'id' => RandomIdentifier::new(),
            'branch_id' => $otherBranch,
            'campus_id' => $otherCampus,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'transfer_correlation_id' => RandomIdentifier::new(),
        ]);
        /** @var Person $foreignBorrower */
        $foreignBorrower = $this->personWithAuthority('canon-borrower-9', [], $otherBranch);

        try {
            app(CirculateBooks::class)->issue($this->librarian, $copy, $foreignBorrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-9');
            $this->fail('a borrower must belong to the copy organization');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.borrower_organization_mismatch', $rejection->errorCode());
        }

        $this->assertSame(0, BookIssuance::query()->where('copy_id', $copy->id)->count());
    }

    public function test_temporal_guards_reject_impossible_issuance_dates(): void
    {
        $copy = $this->addCopy('canon-book-add-8');
        $borrower = $this->personWithAuthority('canon-borrower-10', [], $this->sharedBranchId());

        try {
            app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-03-01', '2026-02-01', 'canon-book-issue-10');
            $this->fail('the due date cannot precede the issue date');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.issuance_due', $rejection->errorCode());
        }

        $issued = app(CirculateBooks::class)->issue($this->librarian, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-11');
        try {
            app(CirculateBooks::class)->returned($this->librarian, BookIssuance::query()->findOrFail($issued['issuance_id']), '2026-01-31', 'canon-book-return-8');
            $this->fail('the return date cannot precede the issue date');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.issuance_returned_on', $rejection->errorCode());
        }
    }

    public function test_an_actor_without_the_books_capability_is_denied_and_only_audited(): void
    {
        $intruder = $this->actorWith('canon-book-intruder', ['resources.asset']);
        $copy = $this->addCopy('canon-book-add-9');
        $borrower = $this->personWithAuthority('canon-borrower-11', [], $this->sharedBranchId());

        try {
            app(CirculateBooks::class)->issue($intruder, $copy, $borrower->id, '2026-02-01', '2026-03-01', 'canon-book-issue-12');
            $this->fail('issuing requires resources.books');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.books_denied', $denial->errorCode());
        }

        $denialEvent = DB::table('audit_events')
            ->where('operation', 'resources.books.issue.denied')
            ->where('actor_id', 'canon-book-intruder')
            ->where('target_id', $copy->id)
            ->first();
        $this->assertNotNull($denialEvent, 'the denial is committed as audit evidence');
        $this->assertSame(0, DB::table('domain_events')->where('audit_event_id', $denialEvent->id)->count(), 'denials emit no domain events');
        $this->assertSame(0, BookIssuance::query()->where('copy_id', $copy->id)->count());
    }
}
