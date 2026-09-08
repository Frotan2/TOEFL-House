<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\AuditRecorder;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditImmutabilityFeatureTest extends TestCase
{
    public function test_recorded_evidence_cannot_be_resaved(): void
    {
        $event = app(AuditRecorder::class)->record('actor-1', 'probe.operation', 'person', '00000000-0000-4000-8000-000000000000', null, ['state' => 'recorded']);
        $this->assertDatabaseHas('domain_events', ['audit_event_id' => $event->id, 'event_type' => 'probe.operation']);

        $event->after_state = ['state' => 'rewritten'];
        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('audit evidence is append-only');
        $event->save();
    }

    public function test_recorded_evidence_cannot_be_deleted_through_the_model(): void
    {
        $event = app(AuditRecorder::class)->record('actor-2', 'probe.operation', 'person', '00000000-0000-4000-8000-000000000001', null, ['state' => 'recorded']);

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('audit evidence is append-only');
        $event->delete();
    }

    public function test_database_rejects_updates_of_audit_evidence(): void
    {
        $event = app(AuditRecorder::class)->record('actor-3', 'probe.operation', 'person', '00000000-0000-4000-8000-000000000002', null, ['state' => 'recorded']);

        // A rejected statement aborts the surrounding transaction, so this
        // attempt runs in its own savepoint and the assertions after it can
        // still read.
        DB::beginTransaction();
        try {
            DB::table('audit_events')->where('id', $event->id)->update(['operation' => 'rewritten.operation']);
            $this->fail('the database must reject rewriting audit evidence');
            DB::rollBack();
        } catch (QueryException $exception) {
            DB::rollBack();
            $this->assertStringContainsString('audit_events is append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'operation' => 'probe.operation']);
    }

    public function test_database_rejects_deletion_of_audit_evidence(): void
    {
        $event = app(AuditRecorder::class)->record('actor-4', 'probe.operation', 'person', '00000000-0000-4000-8000-000000000003', null, ['state' => 'recorded']);

        // A rejected statement aborts the surrounding transaction, so this
        // attempt runs in its own savepoint and later reads still work.
        DB::beginTransaction();
        try {
            DB::table('audit_events')->where('id', $event->id)->delete();
            $this->fail('the database must reject deleting audit evidence');
            DB::rollBack();
        } catch (QueryException $exception) {
            DB::rollBack();
            $this->assertStringContainsString('audit_events is append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_events', ['id' => $event->id]);
    }
}
