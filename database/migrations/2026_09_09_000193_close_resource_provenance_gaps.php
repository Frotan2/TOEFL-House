<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A requested work order may be cancelled before approval. The prior
        // check accidentally required an approver for that valid transition.
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_approval_actor_check');
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_approval_actor_check CHECK (lifecycle_state IN ('requested','cancelled') OR approved_by IS NOT NULL)");

        // Approval is a two-person control, not merely an approval-state flag.
        DB::statement('ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_request_approved_provenance_check CHECK (lifecycle_state NOT IN (\'approved\',\'completed\') OR (approver_one_id IS NOT NULL AND approver_two_id IS NOT NULL AND trim(approver_one_id) <> trim(approver_two_id)))');
        DB::statement('ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_request_completed_provenance_check CHECK (lifecycle_state <> \'completed\' OR (executed_by IS NOT NULL AND disposal_id IS NOT NULL))');

        // The immutable disposal fact must be born from the approved request
        // that authorized it. The request is marked completed only after this
        // fact exists, so the trigger deliberately accepts approved state.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION asset_disposals_provenance_guard() RETURNS trigger AS $fn$
            DECLARE req asset_disposal_requests%ROWTYPE;
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    IF NEW.asset_id IS DISTINCT FROM OLD.asset_id
                        OR NEW.method IS DISTINCT FROM OLD.method
                        OR NEW.reason IS DISTINCT FROM OLD.reason
                        OR NEW.disposed_on IS DISTINCT FROM OLD.disposed_on
                        OR NEW.requested_by IS DISTINCT FROM OLD.requested_by
                        OR NEW.approver_one IS DISTINCT FROM OLD.approver_one
                        OR NEW.approver_two IS DISTINCT FROM OLD.approver_two
                    THEN
                        RAISE EXCEPTION 'asset disposal facts are immutable' USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                SELECT * INTO req
                FROM asset_disposal_requests
                WHERE asset_id = NEW.asset_id
                  AND lifecycle_state IN ('approved','completed')
                  AND method = NEW.method
                  AND reason = NEW.reason
                  AND requested_by = NEW.requested_by
                  AND approver_one_id = NEW.approver_one
                  AND approver_two_id = NEW.approver_two
                ORDER BY id
                LIMIT 1;

                IF req.id IS NULL THEN
                    RAISE EXCEPTION 'asset disposal requires its matching approved request provenance' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('DROP TRIGGER IF EXISTS asset_disposals_provenance_guard_trigger ON asset_disposals');
        DB::statement('CREATE TRIGGER asset_disposals_provenance_guard_trigger BEFORE INSERT OR UPDATE ON asset_disposals FOR EACH ROW EXECUTE FUNCTION asset_disposals_provenance_guard()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS asset_disposals_provenance_guard_trigger ON asset_disposals');
        DB::statement('DROP FUNCTION IF EXISTS asset_disposals_provenance_guard()');
        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT IF EXISTS asset_disposal_request_completed_provenance_check');
        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT IF EXISTS asset_disposal_request_approved_provenance_check');
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_approval_actor_check');
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_approval_actor_check CHECK (lifecycle_state = 'requested' OR approved_by IS NOT NULL)");
    }
};
