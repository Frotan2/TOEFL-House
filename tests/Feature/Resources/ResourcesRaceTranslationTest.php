<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Commands\DisposeAsset;
use App\Modules\Resources\Commands\MaintainAsset;
use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\BookCopy;
use App\Support\Errors\ConcurrencyConflict;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * The pre-check/insert race window, deterministically.
 *
 * Every contended Resources insert is guarded twice: an application
 * pre-check and a partial unique index. A concurrent session that commits
 * between the two provokes the index — a real 23505 that must surface as a
 * retryable 409 concurrency conflict, never as an opaque 500.
 *
 * True wall-clock races cannot be scheduled inside one PHPUnit process, so
 * each test arms a one-shot `DB::beforeExecuting` hook that inserts the
 * rival row immediately before the command's own insert executes. The hook
 * reproduces exactly the interleaving the runtime concurrency races
 * (`npm run verify:concurrency`) produce with two real PostgreSQL backends:
 * the pre-check has already passed and only the index can refuse.
 */
final class ResourcesRaceTranslationTest extends TestCase
{
    use BuildsActors;

    /** Arms a one-shot rival insert immediately before the command's insert into $table. */
    private function armRaceWindow(string $table, array $rivalRow): void
    {
        $armed = true;
        DB::beforeExecuting(function (string $query) use (&$armed, $table, $rivalRow): void {
            if (! $armed || ! str_contains($query, 'insert into "'.$table.'"')) {
                return;
            }
            $armed = false;
            DB::table($table)->insert($rivalRow);
        });
    }

    public function test_a_concurrent_copy_code_registration_is_a_retryable_conflict(): void
    {
        $this->ensureBootstrapAuthority();
        $librarian = $this->grantedActor('race-librarian-1', ['resources.books']);
        $code = 'RACE-COPY-'.RandomIdentifier::new();

        $this->armRaceWindow('book_copies', [
            'id' => RandomIdentifier::new(),
            'code' => $code,
            'title' => 'Rival volume',
            'acquired_on' => '2026-05-01',
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(CirculateBooks::class)->addCopy($librarian, $code, 'Command volume', '2026-05-01', $this->bootstrapBranchId, 'race-book-add-1');
            $this->fail('the rival registration must refuse the losing insert');
        } catch (ConcurrencyConflict $conflict) {
            $this->assertSame('resources.copy_code.concurrent', $conflict->errorCode());
            $this->assertTrue($conflict->retryable(), 'a losing racer may retry with a fresh code');
        }

        // The simulated rival shares the loser's transaction, so the rollback
        // must leave NOTHING behind: no partial registration, no rival ghost.
        // In a real race the rival's committed row survives — proven by the
        // one-winner assertions in `npm run verify:concurrency`.
        $this->assertSame(0, DB::table('book_copies')->where('code', $code)->count(), 'the losing transaction rolls back completely');
    }

    public function test_a_concurrent_asset_registration_is_a_retryable_conflict(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->grantedActor('race-manager-1', ['resources.asset']);
        $code = 'RACE-ASSET-'.RandomIdentifier::new();

        $this->armRaceWindow('assets', [
            'id' => RandomIdentifier::new(),
            'code' => $code,
            'name' => 'Rival asset',
            'category' => 'equipment',
            'location' => 'Room 9',
            'acquired_on' => '2026-05-01',
            'lifecycle_state' => 'in_service',
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(MaintainAsset::class)->register($manager, $code, 'Command asset', 'equipment', 'Room 9', '2026-05-01', $this->bootstrapBranchId, 'race-asset-add-1');
            $this->fail('the rival registration must refuse the losing insert');
        } catch (ConcurrencyConflict $conflict) {
            $this->assertSame('resources.asset_code.concurrent', $conflict->errorCode());
            $this->assertTrue($conflict->retryable());
        }

        $this->assertSame(0, DB::table('assets')->where('code', $code)->count(), 'the losing transaction rolls back completely');
    }

    public function test_a_concurrent_issuance_is_a_retryable_conflict(): void
    {
        $this->ensureBootstrapAuthority();
        $librarian = $this->grantedActor('race-librarian-2', ['resources.books']);
        $borrower = $this->personWithAuthority('race-borrower-1', [], $this->bootstrapBranchId);
        $copyId = app(CirculateBooks::class)->addCopy($librarian, 'RACE-ISSUE-'.RandomIdentifier::new(), 'Raced volume', '2026-05-01', $this->bootstrapBranchId, 'race-book-add-2')['copy_id'];

        $this->armRaceWindow('book_issuances', [
            'id' => RandomIdentifier::new(),
            'copy_id' => $copyId,
            'borrower_person_id' => $borrower->id,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-07-01',
            'lifecycle_state' => 'issued',
            'issued_by' => 'race-librarian-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(CirculateBooks::class)->issue($librarian, BookCopy::query()->findOrFail($copyId), $borrower->id, '2026-06-01', '2026-07-01', 'race-book-issue-1');
            $this->fail('the rival issuance must refuse the losing insert');
        } catch (ConcurrencyConflict $conflict) {
            $this->assertSame('resources.issuance_open.concurrent', $conflict->errorCode());
            $this->assertTrue($conflict->retryable());
        }

        $this->assertSame(0, DB::table('book_issuances')->where('copy_id', $copyId)->where('lifecycle_state', 'issued')->count(), 'the losing transaction rolls back completely');
    }

    public function test_a_concurrent_custody_assignment_is_a_retryable_conflict(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->grantedActor('race-manager-2', ['resources.asset']);
        $custodian = $this->personWithAuthority('race-custodian-1', [], $this->bootstrapBranchId);
        $assetId = app(MaintainAsset::class)->register($manager, 'RACE-CUSTODY-'.RandomIdentifier::new(), 'Raced asset', 'equipment', 'Room 3', '2026-05-01', $this->bootstrapBranchId, 'race-asset-add-2')['asset_id'];

        $this->armRaceWindow('custodies', [
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'custodian_person_id' => $custodian->id,
            'assigned_on' => '2026-06-01',
            'assigned_by' => 'race-manager-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(MaintainAsset::class)->assignCustody($manager, Asset::query()->findOrFail($assetId), $custodian->id, '2026-06-01', 'race-custody-assign-1');
            $this->fail('the rival custody must refuse the losing insert');
        } catch (ConcurrencyConflict $conflict) {
            $this->assertSame('resources.custody_open.concurrent', $conflict->errorCode());
            $this->assertTrue($conflict->retryable());
        }

        $this->assertSame(0, DB::table('custodies')->where('asset_id', $assetId)->whereNull('released_on')->count(), 'the losing transaction rolls back completely');
    }

    public function test_a_concurrent_disposal_request_is_a_retryable_conflict(): void
    {
        $this->ensureBootstrapAuthority();
        $manager = $this->grantedActor('race-manager-3', ['resources.asset', 'resources.dispose_request']);
        $assetId = app(MaintainAsset::class)->register($manager, 'RACE-DISPOSAL-'.RandomIdentifier::new(), 'Raced disposal asset', 'equipment', 'Room 4', '2026-05-01', $this->bootstrapBranchId, 'race-asset-add-3')['asset_id'];

        $this->armRaceWindow('asset_disposal_requests', [
            'id' => RandomIdentifier::new(),
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'rival request',
            'lifecycle_state' => 'requested',
            'requested_by' => RandomIdentifier::new(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(DisposeAsset::class)->request($manager, Asset::query()->findOrFail($assetId), 'sale', 'command request', 'race-disposal-request-1');
            $this->fail('the rival request must refuse the losing insert');
        } catch (ConcurrencyConflict $conflict) {
            $this->assertSame('resources.disposal_active.concurrent', $conflict->errorCode());
            $this->assertTrue($conflict->retryable());
        }

        $this->assertSame(0, DB::table('asset_disposal_requests')->where('asset_id', $assetId)->count(), 'the losing transaction rolls back completely');
    }

    public function test_the_api_surfaces_a_concurrent_conflict_as_a_retryable_409(): void
    {
        $this->ensureBootstrapAuthority();
        $librarian = $this->personWithAuthority('race-librarian-3', ['resources.books']);
        $user = $this->userForActor($librarian->id, 'race-librarian-3');
        $this->post('/login', ['username' => $user->username, 'password' => 'employee-password-1'])->assertRedirect();

        $code = 'RACE-API-'.RandomIdentifier::new();
        $this->armRaceWindow('book_copies', [
            'id' => RandomIdentifier::new(),
            'code' => $code,
            'title' => 'Rival API volume',
            'acquired_on' => '2026-05-01',
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/resources/books', [
            'code' => $code,
            'title' => 'API volume',
            'acquired_on' => '2026-05-01',
            'branch_id' => $this->bootstrapBranchId,
        ])->assertStatus(409)->assertJson([
            'error' => 'resources.copy_code.concurrent',
            'category' => 'concurrency_conflict',
            'retryable' => true,
        ])->assertJsonStructure(['error', 'category', 'message', 'correlation_id', 'retryable']);

        $this->assertSame(0, DB::table('book_copies')->where('code', $code)->count(), 'the losing transaction rolls back completely');
    }
}
