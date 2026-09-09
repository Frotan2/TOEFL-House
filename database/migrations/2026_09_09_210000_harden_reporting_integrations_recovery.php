<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The application already performs idempotent read-before-write checks,
        // but the database must remain the final concurrency authority.
        // Rejected inbound evidence is intentionally excluded so a corrected
        // retry with the same external id can be retained as a new accepted event.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX inbound_events_endpoint_external_id_accepted_unique
            ON inbound_events (endpoint_id, external_id)
            WHERE status <> 'rejected'
        SQL);

        // A delivery identity never changes when it is retried, dead-lettered,
        // or manually replayed. Keep one physical delivery row per endpoint key.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX integration_deliveries_endpoint_idempotency_unique
            ON integration_deliveries (endpoint_id, idempotency_key)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE event_consumer_receipts
            ADD COLUMN replay_count INTEGER NOT NULL DEFAULT 0
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inbound_events_endpoint_external_id_accepted_unique');
        DB::statement('DROP INDEX IF EXISTS integration_deliveries_endpoint_idempotency_unique');
        DB::statement(<<<'SQL'
            ALTER TABLE event_consumer_receipts
            DROP COLUMN IF EXISTS replay_count
        SQL);
    }
};
