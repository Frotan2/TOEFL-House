<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\AuditRecorder;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class AuditImmutabilityFeatureTest extends TestCase
{
    use BuildsActors;

    /**
     * Evidence carries the actor into the append-only audit and outbox
     * tables, both of which reference the people registry; probe actors
     * must therefore be real fixture persons.
     */
    private function probeEvent(string $actorId, string $targetId)
    {
        $this->personWithAuthority($actorId, []);

        return app(AuditRecorder::class)->record($actorId, 'probe.operation', 'person', $targetId, null, ['state' => 'recorded']);
    }

    public function test_recorded_evidence_cannot_be_resaved(): void
    {
        $event = $this->probeEvent('actor-1', '00000000-0000-4000-8000-000000000000');
        $this->assertDatabaseHas('domain_events', ['audit_event_id' => $event->id, 'event_type' => 'probe.operation']);

        $event->after_state = ['state' => 'rewritten'];
        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('audit evidence is append-only');
        $event->save();
    }

    public function test_recorded_evidence_cannot_be_deleted_through_the_model(): void
    {
        $event = $this->probeEvent('actor-2', '00000000-0000-4000-8000-000000000001');

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('audit evidence is append-only');
        $event->delete();
    }

    public function test_database_rejects_updates_of_audit_evidence(): void
    {
        $event = $this->probeEvent('actor-3', '00000000-0000-4000-8000-000000000002');

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
        $event = $this->probeEvent('actor-4', '00000000-0000-4000-8000-000000000003');

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
