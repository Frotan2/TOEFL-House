<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Resources\Models\Asset;
use App\Modules\Resources\Models\AssetDisposalRequest;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssuance;
use App\Modules\Resources\Models\WorkOrder;
use App\Support\Identifiers\RandomIdentifier;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Canonical JSON API mutation contract for Library & Resources: transport
 * behavior over HTTP for the endpoints the React console uses. Covers
 * anonymous rejection, success statuses, the DomainError JSON error
 * contract (403/409 with code, category, correlation and retryability),
 * Laravel validation (422), unknown identities (404), denial audits, and
 * Idempotency-Key semantics including hostile header values.
 */
final class ResourcesApiMutationContractTest extends TestCase
{
    use BuildsActors;

    private function login(string $personId, array $capabilities, ?string $homeBranchId = null): void
    {
        $this->personWithAuthority($personId, $capabilities, $homeBranchId);
        $user = $this->userForActor($personId);
        $this->post('/login', ['username' => $user->username, 'password' => 'employee-password-1'])->assertRedirect();
    }

    private const ERROR_STRUCTURE = ['error', 'category', 'message', 'correlation_id', 'retryable'];

    public function test_anonymous_requests_get_the_authentication_contract_and_write_nothing(): void
    {
        $this->getJson('/api/v1/resources/workspace')
            ->assertStatus(401)
            ->assertJson(['error' => 'authentication_required']);

        $this->postJson('/api/v1/resources/books', [
            'code' => 'ANON-1', 'title' => 'Anonymous', 'acquired_on' => '2026-01-10', 'branch_id' => RandomIdentifier::new(),
        ], ['Idempotency-Key' => 'anon-books-1'])
            ->assertStatus(401)
            ->assertJson(['error' => 'authentication_required']);

        $this->assertDatabaseMissing('book_copies', ['code' => 'ANON-1']);
    }

    public function test_book_circulation_runs_over_http_with_the_recorded_statuses(): void
    {
        $this->login('api-librarian', ['resources.books']);
        $this->personWithAuthority('api-borrower', [], $this->bootstrapBranchId);

        $this->postJson('/api/v1/resources/books', [
            'code' => 'API-BOOK-1', 'title' => 'API circulation', 'acquired_on' => '2026-01-10', 'branch_id' => $this->bootstrapBranchId,
        ], ['Idempotency-Key' => 'api-book-add-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);

        $copy = BookCopy::query()->where('code', 'API-BOOK-1')->firstOrFail();

        $this->postJson("/api/v1/resources/books/{$copy->id}/issue", [
            'borrower_id' => 'api-borrower', 'issued_on' => '2026-02-01', 'due_on' => '2026-03-01',
        ], ['Idempotency-Key' => 'api-book-issue-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'issued']);

        $issuance = BookIssuance::query()->where('copy_id', $copy->id)->firstOrFail();

        $this->postJson("/api/v1/resources/issuances/{$issuance->id}/return", [
            'returned_on' => '2026-02-20',
        ], ['Idempotency-Key' => 'api-book-return-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'returned']);

        $this->assertDatabaseHas('book_issuances', ['id' => $issuance->id, 'lifecycle_state' => 'returned', 'returned_on' => '2026-02-20']);

        // The lifecycle guard reaches the transport with the domain contract.
        $this->postJson("/api/v1/resources/issuances/{$issuance->id}/loss", [
            'loss_evidence' => 'too late',
        ], ['Idempotency-Key' => 'api-book-loss-1'])
            ->assertStatus(409)
            ->assertJsonStructure(self::ERROR_STRUCTURE)
            ->assertJson(['error' => 'resources.issuance_transition_forbidden', 'retryable' => false]);
    }

    public function test_asset_custody_staged_disposal_and_work_orders_run_over_http(): void
    {
        $this->login('api-manager', ['resources.asset', 'resources.dispose_request', 'resources.dispose_approve', 'facilities.work']);
        $this->login('api-approver', ['resources.dispose_approve', 'facilities.work_approve']);
        $this->personWithAuthority('api-custodian', [], $this->bootstrapBranchId);
        $this->post('/login', ['username' => 'employee-api-manager', 'password' => 'employee-password-1'])->assertRedirect();

        $this->postJson('/api/v1/resources/assets', [
            'code' => 'API-ASSET-1', 'name' => 'API projector', 'category' => 'electronics',
            'location' => 'Room 1', 'acquired_on' => '2026-01-15', 'branch_id' => $this->bootstrapBranchId,
        ], ['Idempotency-Key' => 'api-asset-register-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);

        $assetId = Asset::query()->where('code', 'API-ASSET-1')->value('id');

        $this->postJson("/api/v1/resources/assets/{$assetId}/custody", [
            'custodian_id' => 'api-custodian', 'assigned_on' => '2026-02-01',
        ], ['Idempotency-Key' => 'api-custody-assign-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'assigned']);

        $this->postJson("/api/v1/resources/assets/{$assetId}/custody/release", [
            'released_on' => '2026-02-15',
        ], ['Idempotency-Key' => 'api-custody-release-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'released']);

        $this->postJson("/api/v1/resources/assets/{$assetId}/disposal", [
            'method' => 'sale', 'reason' => 'superseded',
        ], ['Idempotency-Key' => 'api-disposal-request-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'requested']);

        $disposalId = AssetDisposalRequest::query()->where('asset_id', $assetId)->firstOrFail()->id;

        // Self-approval is denied over HTTP with the domain contract and an audit trail.
        $this->postJson("/api/v1/resources/disposals/{$disposalId}/approve", [], ['Idempotency-Key' => 'api-disposal-approve-self'])
            ->assertStatus(403)
            ->assertJsonStructure(self::ERROR_STRUCTURE)
            ->assertJson(['error' => 'resources.disposal_not_independent']);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'resources.disposal.approve.denied',
            'actor_id' => 'api-manager',
            'target_id' => $disposalId,
        ]);

        // The requester withdraws; the asset is freed for a corrected request.
        $this->postJson("/api/v1/resources/disposals/{$disposalId}/withdraw", [], ['Idempotency-Key' => 'api-disposal-withdraw-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'withdrawn']);
        $this->assertDatabaseHas('asset_disposal_requests', ['id' => $disposalId, 'lifecycle_state' => 'withdrawn', 'withdrawn_by' => 'api-manager']);

        $this->postJson("/api/v1/resources/assets/{$assetId}/disposal", [
            'method' => 'scrap', 'reason' => 'corrected',
        ], ['Idempotency-Key' => 'api-disposal-request-2'])
            ->assertStatus(201)
            ->assertJson(['status' => 'requested']);

        // Facilities work: request, independent approval, start, completion with evidence.
        $this->postJson('/api/v1/resources/work-orders', [
            'facility_note' => 'Library HVAC', 'description' => 'Replace filter', 'branch_id' => $this->bootstrapBranchId,
        ], ['Idempotency-Key' => 'api-work-request-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'requested']);

        $orderId = WorkOrder::query()->where('facility_note', 'Library HVAC')->firstOrFail()->id;

        $this->post('/login', ['username' => 'employee-api-approver', 'password' => 'employee-password-1'])->assertRedirect();
        $this->postJson("/api/v1/resources/work-orders/{$orderId}/approve", [], ['Idempotency-Key' => 'api-work-approve-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'approved']);

        $this->post('/login', ['username' => 'employee-api-manager', 'password' => 'employee-password-1'])->assertRedirect();
        $this->postJson("/api/v1/resources/work-orders/{$orderId}/start", [], ['Idempotency-Key' => 'api-work-start-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'started']);
        $this->postJson("/api/v1/resources/work-orders/{$orderId}/complete", [
            'evidence_ref' => 'evidence/library/hvac-1',
        ], ['Idempotency-Key' => 'api-work-complete-1'])
            ->assertStatus(200)
            ->assertJson(['status' => 'completed']);

        $this->assertDatabaseHas('work_orders', ['id' => $orderId, 'lifecycle_state' => 'completed', 'evidence_ref' => 'evidence/library/hvac-1']);
    }

    public function test_unauthorized_mutations_get_the_403_contract_are_audited_and_write_nothing(): void
    {
        $this->login('api-nobody', []);

        $this->postJson('/api/v1/resources/books', [
            'code' => 'DENIED-1', 'title' => 'Denied', 'acquired_on' => '2026-01-10', 'branch_id' => $this->bootstrapBranchId,
        ], ['Idempotency-Key' => 'api-denied-books-1'])
            ->assertStatus(403)
            ->assertJsonStructure(self::ERROR_STRUCTURE)
            ->assertJson(['error' => 'resources.books_denied', 'retryable' => false]);

        $this->assertDatabaseMissing('book_copies', ['code' => 'DENIED-1']);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'resources.books.add.denied',
            'actor_id' => 'api-nobody',
        ]);
    }

    public function test_validation_and_unknown_identities_use_their_own_status_contracts(): void
    {
        $this->login('api-librarian-v', ['resources.books']);

        $this->postJson('/api/v1/resources/books', ['title' => 'No code'], ['Idempotency-Key' => 'api-invalid-books-1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'acquired_on', 'branch_id']);

        $this->postJson('/api/v1/resources/books', [
            'code' => 'API-BOOK-V', 'title' => 'Bad date', 'acquired_on' => 'not-a-date', 'branch_id' => $this->bootstrapBranchId,
        ], ['Idempotency-Key' => 'api-invalid-books-2'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acquired_on']);

        $this->postJson('/api/v1/resources/books/'.RandomIdentifier::new().'/issue', [
            'borrower_id' => 'whoever', 'issued_on' => '2026-02-01', 'due_on' => '2026-03-01',
        ], ['Idempotency-Key' => 'api-missing-copy-1'])
            ->assertStatus(404);

        $this->assertDatabaseMissing('book_copies', ['code' => 'API-BOOK-V']);
    }

    public function test_idempotency_keys_replay_identical_requests_and_reject_conflicting_payloads(): void
    {
        $this->login('api-librarian-i', ['resources.books']);

        $payload = [
            'code' => 'API-IDEM-1', 'title' => 'Idempotent', 'acquired_on' => '2026-01-10', 'branch_id' => $this->bootstrapBranchId,
        ];

        $this->postJson('/api/v1/resources/books', $payload, ['Idempotency-Key' => 'api-idem-key-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);
        $this->postJson('/api/v1/resources/books', $payload, ['Idempotency-Key' => 'api-idem-key-1'])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);

        $this->assertSame(1, BookCopy::query()->where('code', 'API-IDEM-1')->count(), 'a replayed request records the copy exactly once');

        $this->postJson('/api/v1/resources/books', ['code' => 'API-IDEM-2'] + array_slice($payload, 1), ['Idempotency-Key' => 'api-idem-key-1'])
            ->assertStatus(409)
            ->assertJsonStructure(self::ERROR_STRUCTURE)
            ->assertJson(['error' => 'idempotency.conflicting_payload']);

        $this->assertDatabaseMissing('book_copies', ['code' => 'API-IDEM-2']);
    }

    public function test_hostile_idempotency_key_headers_fall_back_to_generated_keys(): void
    {
        $this->login('api-librarian-h', ['resources.books']);

        $payload = [
            'code' => 'API-HOSTILE-1', 'title' => 'Hostile key', 'acquired_on' => '2026-01-10', 'branch_id' => $this->bootstrapBranchId,
        ];

        // Invalid charset and too short: the server-generated key takes over,
        // the request still succeeds, and nothing crashes or double-writes.
        $this->postJson('/api/v1/resources/books', $payload, ['Idempotency-Key' => 'bad key with spaces!'])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);
        $this->assertSame(1, BookCopy::query()->where('code', 'API-HOSTILE-1')->count());

        $this->postJson('/api/v1/resources/books', ['code' => 'API-HOSTILE-2'] + array_slice($payload, 1), ['Idempotency-Key' => 'short'])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);

        // A 129-character key exceeds the contract and must also fall back.
        $this->postJson('/api/v1/resources/books', ['code' => 'API-HOSTILE-3'] + array_slice($payload, 1), ['Idempotency-Key' => str_repeat('a', 129)])
            ->assertStatus(201)
            ->assertJson(['status' => 'recorded']);
    }
}
