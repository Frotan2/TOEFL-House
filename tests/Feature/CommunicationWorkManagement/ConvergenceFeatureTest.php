<?php

declare(strict_types=1);

namespace Tests\Feature\CommunicationWorkManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ConvergenceFeatureTest extends TestCase
{
    public function test_communication_projection_has_canonical_thread_and_recipient_state(): void
    {
        $this->assertTrue(Schema::hasTable('communication_threads'));
        $this->assertTrue(Schema::hasTable('communication_thread_participants'));
        $this->assertTrue(Schema::hasTable('notification_recipient_states'));
        $this->assertTrue(Schema::hasColumn('messages', 'thread_id'));

        // PostgreSQL limits identifiers to 63 bytes, so Laravel's generated
        // 71-byte name is correctly truncated by the database. Assert the
        // invariant the index exists to protect, rather than a non-portable
        // implementation spelling of its generated identifier.
        $indexes = DB::select("SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'notification_recipient_states'");
        $this->assertTrue(
            collect($indexes)->contains(static fn (object $index): bool => str_contains((string) $index->indexdef, 'UNIQUE INDEX')
                && str_contains((string) $index->indexdef, '(notification_id, recipient_actor_id)')),
            'notification recipient state must be unique per notification and recipient',
        );
    }

    public function test_work_items_expose_sla_foundation_without_replacing_source_lifecycle(): void
    {
        foreach (['sla_policy_key', 'sla_state', 'escalation_level', 'last_escalated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('work_items', $column), "missing work_items.$column");
        }

        $constraints = DB::select("SELECT conname FROM pg_constraint WHERE conrelid = 'work_items'::regclass");
        $names = array_map(static fn ($row): string => (string) $row->conname, $constraints);
        $this->assertContains('work_items_sla_state_check', $names);
        $this->assertContains('work_items_escalation_check', $names);
    }

    public function test_queue_scope_uniqueness_is_database_enforced_for_concurrent_grants(): void
    {
        $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'work_queue_memberships'");
        $names = array_map(static fn ($row): string => (string) $row->indexname, $indexes);
        $this->assertContains('work_queue_memberships_org_unique', $names);
        $this->assertContains('work_queue_memberships_scoped_unique', $names);
        $this->assertNotContains('work_queue_memberships_global_unique', $names);
    }
}
