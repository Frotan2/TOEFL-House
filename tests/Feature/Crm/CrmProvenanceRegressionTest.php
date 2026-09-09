<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Modules\Crm\Commands\CaptureVisitor;
use App\Modules\Crm\Domain\CrmAccess;
use App\Modules\Crm\Models\Visitor;
use App\Modules\Crm\Queries\VisitorListQuery;
use App\Modules\Crm\Queries\VisitorTimelineQuery;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class CrmProvenanceRegressionTest extends TestCase
{
    use BuildsActors;

    public function test_capture_without_branch_uses_the_actor_home_branch_as_canonical_provenance(): void
    {
        $actor = $this->actorWithStructureCapabilities('crm-provenance-capture', ['crm.visitor']);

        $result = app(CaptureVisitor::class)->capture(
            $actor,
            null,
            'Home Branch Lead',
            null,
            'provenance@example.com',
            'email',
            'online',
            null,
            null,
            null,
            'TOEFL',
            null,
            'crm-provenance-capture-1',
        );

        $visitor = Visitor::query()->findOrFail($result['visitor_id']);
        $this->assertSame($this->bootstrapBranchId(), $visitor->origin_branch_id);
    }

    public function test_record_operations_fail_closed_when_legacy_provenance_is_unknown(): void
    {
        $actor = $this->actorWithStructureCapabilities('crm-provenance-unknown', ['crm.visitor', 'crm.followup']);
        $access = app(CrmAccess::class);

        foreach (['crm.visitor', 'crm.followup'] as $capability) {
            try {
                $access->require($actor, $capability, null, 'crm.unknown_provenance_denied');
                $this->fail('unknown CRM record provenance must not be authorized');
            } catch (AuthorizationDenied $denial) {
                $this->assertSame('crm.unknown_provenance_denied', $denial->errorCode());
            }
        }
    }

    public function test_unknown_provenance_is_excluded_from_directory_and_timeline_read_models(): void
    {
        $actor = $this->actorWithStructureCapabilities('crm-provenance-reads', ['crm.visitor']);
        $capture = app(CaptureVisitor::class)->capture(
            $actor,
            null,
            'Known Provenance',
            null,
            'known-provenance@example.com',
            'email',
            'online',
            null,
            null,
            null,
            null,
            null,
            'crm-provenance-read-1',
        );
        $visitor = Visitor::query()->findOrFail($capture['visitor_id']);

        $rows = app(VisitorListQuery::class)->search(null, [
            'branch_ids' => [$this->bootstrapBranchId()],
            'include_unassigned' => true,
        ]);
        $this->assertCount(1, array_filter($rows, fn (array $row): bool => $row['id'] === $visitor->id));

        $legacy = clone $visitor;
        $legacy->origin_branch_id = null;
        try {
            app(VisitorListQuery::class)->detail($legacy);
            $this->fail('unknown provenance must not be exposed through visitor detail');
        } catch (\App\Support\Errors\BusinessRejection $rejection) {
            $this->assertSame('crm.visitor_provenance_unknown', $rejection->errorCode());
        }

        try {
            app(VisitorTimelineQuery::class)->for($legacy);
            $this->fail('unknown provenance must not be exposed through visitor timeline');
        } catch (\App\Support\Errors\BusinessRejection $rejection) {
            $this->assertSame('crm.visitor_provenance_unknown', $rejection->errorCode());
        }
    }
}