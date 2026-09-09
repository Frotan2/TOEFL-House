<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Resources\Commands\CirculateBooks;
use App\Modules\Resources\Commands\MaintainWorkOrder;
use App\Modules\Resources\Models\BookCopy;
use App\Modules\Resources\Models\BookIssuance;
use App\Modules\Resources\Models\WorkOrder;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class ResourceInvariantFeatureTest extends TestCase
{
    use BuildsActors;

    private function bookCopy(string $branchId): BookCopy
    {
        return BookCopy::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $branchId,
            'code' => 'LIB-'.RandomIdentifier::new(),
            'title' => 'Invariant Test Copy',
            'acquired_on' => '2026-09-01',
        ]);
    }

    public function test_return_idempotency_key_cannot_replay_a_different_return_date(): void
    {
        $this->ensureBootstrapAuthority();
        $actor = $this->actorWithStructureCapabilities('resources-idem', ['resources.books']);
        $borrower = $this->personWithAuthority('resources-borrower', []);
        $copy = $this->bookCopy($this->bootstrapBranchId);

        $issued = app(CirculateBooks::class)->issue($actor, $copy, $borrower->id, '2026-09-09', '2026-09-20', 'resources-idem-issue');
        $issuance = BookIssuance::query()->findOrFail($issued['issuance_id']);

        app(CirculateBooks::class)->returned($actor, $issuance, '2026-09-10', 'resources-idem-return');

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('idempotency key reused with a different payload');
        app(CirculateBooks::class)->returned($actor, $issuance, '2026-09-11', 'resources-idem-return');
    }

    public function test_completion_idempotency_key_cannot_replay_a_different_evidence_reference(): void
    {
        $this->ensureBootstrapAuthority();
        $requester = $this->actorWithStructureCapabilities('work-requester', ['facilities.work']);
        $approver = $this->actorWithStructureCapabilities('work-approver', ['facilities.work_approve']);

        $created = app(MaintainWorkOrder::class)->request($requester, 'Library HVAC', 'Replace filter', $this->bootstrapBranchId, 'work-idem-request');
        $order = WorkOrder::query()->findOrFail($created['work_order_id']);
        app(MaintainWorkOrder::class)->approve($approver, $order, 'work-idem-approve');
        $order->refresh();
        app(MaintainWorkOrder::class)->start($requester, $order, 'work-idem-start');
        $order->refresh();

        app(MaintainWorkOrder::class)->complete($requester, $order, 'evidence/library/001', 'work-idem-complete');

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('idempotency key reused with a different payload');
        app(MaintainWorkOrder::class)->complete($requester, $order, 'evidence/library/002', 'work-idem-complete');
    }

    public function test_database_rejects_impossible_issuance_and_custody_dates(): void
    {
        $this->ensureBootstrapAuthority();
        $borrower = $this->personWithAuthority('resources-db-borrower', []);
        $copy = $this->bookCopy($this->bootstrapBranchId);
        $issuanceId = RandomIdentifier::new();

        DB::table('book_issuances')->insert([
            'id' => $issuanceId,
            'copy_id' => $copy->id,
            'borrower_person_id' => $borrower->id,
            'issued_on' => '2026-09-10',
            'due_on' => '2026-09-20',
            'returned_on' => null,
            'lifecycle_state' => 'issued',
            'loss_evidence' => null,
            'issued_by' => $borrower->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('book_issuances')->where('id', $issuanceId)->update(['returned_on' => '2026-09-09', 'lifecycle_state' => 'returned']);
    }

    public function test_database_rejects_multiple_active_disposal_requests(): void
    {
        $this->ensureBootstrapAuthority();
        $assetId = RandomIdentifier::new();
        DB::table('assets')->insert([
            'id' => $assetId,
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $this->bootstrapBranchId,
            'code' => 'AST-'.RandomIdentifier::new(),
            'name' => 'Invariant Asset',
            'category' => 'equipment',
            'location' => 'library',
            'acquired_on' => '2026-09-01',
            'lifecycle_state' => 'in_service',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $base = [
            'asset_id' => $assetId,
            'method' => 'scrap',
            'reason' => 'worn',
            'lifecycle_state' => 'requested',
            'requested_by' => 'requester-a',
            'approver_one_id' => null,
            'approver_two_id' => null,
            'executed_by' => null,
            'disposal_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('asset_disposal_requests')->insert(['id' => RandomIdentifier::new()] + $base);

        $this->expectException(QueryException::class);
        DB::table('asset_disposal_requests')->insert(['id' => RandomIdentifier::new()] + $base);
    }
}
