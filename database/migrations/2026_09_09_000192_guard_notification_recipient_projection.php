<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Keep the per-recipient notification projection present for every new notification. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION notification_recipient_state_projection() RETURNS trigger AS $fn$
            BEGIN
                INSERT INTO notification_recipient_states
                    (id, notification_id, recipient_actor_id, lifecycle_state, read_at, dismissed_at, created_at, updated_at)
                VALUES
                    (md5(NEW.id || ':' || NEW.recipient_actor_id)::uuid::text, NEW.id, NEW.recipient_actor_id, NEW.lifecycle_state, NEW.read_at, NEW.dismissed_at, NEW.created_at, NEW.updated_at)
                ON CONFLICT (notification_id, recipient_actor_id) DO NOTHING;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('CREATE TRIGGER notifications_recipient_state_projection AFTER INSERT ON notifications FOR EACH ROW EXECUTE FUNCTION notification_recipient_state_projection()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS notifications_recipient_state_projection ON notifications');
        DB::statement('DROP FUNCTION IF EXISTS notification_recipient_state_projection()');
    }
};
