<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converges communication recipient state/thread projections and strengthens
 * work-management SLA/queue invariants without creating a new business truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_threads', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('subject')->nullable();
            $table->string('scope_type');
            $table->char('organization_id', 36);
            $table->char('branch_id', 36)->nullable();
            $table->string('lifecycle_state');
            $table->char('created_by', 36);
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('people');
            $table->index(['organization_id', 'lifecycle_state', 'last_activity_at']);
            $table->index(['branch_id', 'lifecycle_state', 'last_activity_at']);
        });
        DB::statement("ALTER TABLE communication_threads ADD CONSTRAINT communication_threads_scope_check CHECK ((scope_type = 'organization' AND branch_id IS NULL) OR (scope_type = 'branch' AND branch_id IS NOT NULL))");
        DB::statement("ALTER TABLE communication_threads ADD CONSTRAINT communication_threads_state_check CHECK (lifecycle_state IN ('open','closed'))");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION communication_threads_provenance_guard() RETURNS trigger AS $fn$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM organizations
                    WHERE id = NEW.organization_id AND lifecycle_state = 'active'
                ) THEN
                    RAISE EXCEPTION 'communication thread organization provenance must name an active organization'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.branch_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1
                      FROM branches b
                      JOIN campus_assignments ca ON ca.branch_id = b.id
                      JOIN campuses c ON c.id = ca.campus_id
                     WHERE b.id = NEW.branch_id
                       AND b.lifecycle_state = 'active'
                       AND ca.effective_from <= CURRENT_DATE
                       AND (ca.effective_to IS NULL OR ca.effective_to > CURRENT_DATE)
                       AND c.organization_id = NEW.organization_id
                       AND c.lifecycle_state = 'active'
                ) THEN
                    RAISE EXCEPTION 'communication thread branch and organization provenance must agree'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('CREATE TRIGGER communication_threads_provenance_trigger BEFORE INSERT OR UPDATE ON communication_threads FOR EACH ROW EXECUTE FUNCTION communication_threads_provenance_guard()');

        Schema::create('communication_thread_participants', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('thread_id', 36);
            $table->char('actor_id', 36);
            $table->string('participant_role');
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->timestamps();
            $table->foreign('thread_id')->references('id')->on('communication_threads');
            $table->foreign('actor_id')->references('id')->on('people');
            $table->unique(['thread_id', 'actor_id']);
            $table->index(['actor_id', 'left_at']);
        });
        DB::statement("ALTER TABLE communication_thread_participants ADD CONSTRAINT communication_thread_participants_role_check CHECK (participant_role IN ('participant','sender','recipient'))");
        DB::statement("ALTER TABLE communication_thread_participants ADD CONSTRAINT communication_thread_participants_window_check CHECK (left_at IS NULL OR left_at >= joined_at)");

        Schema::table('messages', function (Blueprint $table): void {
            $table->char('thread_id', 36)->nullable();
            $table->foreign('thread_id')->references('id')->on('communication_threads');
            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('notification_recipient_states', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('notification_id', 36);
            $table->char('recipient_actor_id', 36);
            $table->string('lifecycle_state');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestamps();
            $table->foreign('notification_id')->references('id')->on('notifications');
            $table->foreign('recipient_actor_id')->references('id')->on('people');
            $table->unique(['notification_id', 'recipient_actor_id']);
            $table->index(['recipient_actor_id', 'lifecycle_state', 'created_at']);
        });
        DB::statement("ALTER TABLE notification_recipient_states ADD CONSTRAINT notification_recipient_states_state_check CHECK ((lifecycle_state = 'unread' AND read_at IS NULL AND dismissed_at IS NULL) OR (lifecycle_state = 'read' AND read_at IS NOT NULL AND dismissed_at IS NULL) OR (lifecycle_state = 'dismissed' AND dismissed_at IS NOT NULL))");

        DB::statement(<<<'SQL'
            INSERT INTO notification_recipient_states
                (id, notification_id, recipient_actor_id, lifecycle_state, read_at, dismissed_at, created_at, updated_at)
            SELECT md5(n.id || ':' || n.recipient_actor_id)::uuid::text,
                   n.id,
                   n.recipient_actor_id,
                   n.lifecycle_state,
                   n.read_at,
                   n.dismissed_at,
                   n.created_at,
                   n.updated_at
              FROM notifications n
             WHERE NOT EXISTS (
                SELECT 1 FROM notification_recipient_states rs
                 WHERE rs.notification_id = n.id AND rs.recipient_actor_id = n.recipient_actor_id
             )
            SQL);

        Schema::table('work_items', function (Blueprint $table): void {
            $table->string('sla_policy_key')->nullable();
            $table->string('sla_state')->default('not_applicable');
            $table->unsignedSmallInteger('escalation_level')->default(0);
            $table->timestampTz('last_escalated_at')->nullable();
            $table->index(['sla_state', 'due_at']);
            $table->index(['queue_key', 'sla_state', 'due_at']);
        });
        DB::statement("ALTER TABLE work_items ADD CONSTRAINT work_items_sla_state_check CHECK (sla_state IN ('not_applicable','on_track','at_risk','breached'))");
        DB::statement("ALTER TABLE work_items ADD CONSTRAINT work_items_escalation_check CHECK (escalation_level >= 0)");

        DB::statement('CREATE UNIQUE INDEX work_queue_memberships_scoped_unique ON work_queue_memberships (actor_id, queue_key, organization_id, branch_id) WHERE branch_id IS NOT NULL');
        DB::statement('DROP INDEX IF EXISTS work_queue_memberships_global_unique');
        DB::statement('CREATE UNIQUE INDEX work_queue_memberships_org_unique ON work_queue_memberships (actor_id, queue_key, organization_id) WHERE branch_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS work_queue_memberships_org_unique');
        DB::statement('DROP INDEX IF EXISTS work_queue_memberships_scoped_unique');
        DB::statement('CREATE UNIQUE INDEX work_queue_memberships_global_unique ON work_queue_memberships (actor_id, queue_key, organization_id) WHERE branch_id IS NULL');
        DB::statement('ALTER TABLE work_items DROP CONSTRAINT IF EXISTS work_items_escalation_check');
        DB::statement('ALTER TABLE work_items DROP CONSTRAINT IF EXISTS work_items_sla_state_check');
        Schema::table('work_items', function (Blueprint $table): void {
            $table->dropIndex(['sla_state', 'due_at']);
            $table->dropIndex(['queue_key', 'sla_state', 'due_at']);
            $table->dropColumn(['sla_policy_key', 'sla_state', 'escalation_level', 'last_escalated_at']);
        });
        DB::statement('ALTER TABLE notification_recipient_states DROP CONSTRAINT IF EXISTS notification_recipient_states_state_check');
        Schema::dropIfExists('notification_recipient_states');
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['thread_id']);
            $table->dropIndex(['thread_id', 'created_at']);
            $table->dropColumn('thread_id');
        });
        DB::statement('DROP TRIGGER IF EXISTS communication_threads_provenance_trigger ON communication_threads');
        DB::statement('DROP FUNCTION IF EXISTS communication_threads_provenance_guard()');
        DB::statement('ALTER TABLE communication_threads DROP CONSTRAINT IF EXISTS communication_threads_state_check');
        DB::statement('ALTER TABLE communication_threads DROP CONSTRAINT IF EXISTS communication_threads_scope_check');
        DB::statement('ALTER TABLE communication_thread_participants DROP CONSTRAINT IF EXISTS communication_thread_participants_role_check');
        DB::statement('ALTER TABLE communication_thread_participants DROP CONSTRAINT IF EXISTS communication_thread_participants_window_check');
        Schema::dropIfExists('communication_thread_participants');
        Schema::dropIfExists('communication_threads');
    }
};
