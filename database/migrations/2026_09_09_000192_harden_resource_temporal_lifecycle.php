<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION work_orders_history_guard() RETURNS trigger AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'work order history cannot be deleted' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state IN ('completed','cancelled') THEN
                    RAISE EXCEPTION 'completed or cancelled work orders are retained history' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.organization_id IS DISTINCT FROM OLD.organization_id
                    OR NEW.originating_branch_id IS DISTINCT FROM OLD.originating_branch_id
                    OR NEW.facility_note IS DISTINCT FROM OLD.facility_note
                    OR NEW.description IS DISTINCT FROM OLD.description
                    OR NEW.requested_by IS DISTINCT FROM OLD.requested_by
                THEN
                    RAISE EXCEPTION 'work order request facts are immutable' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.approved_by IS NOT NULL AND NEW.approved_by IS DISTINCT FROM OLD.approved_by THEN
                    RAISE EXCEPTION 'work order approval actor is immutable' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.evidence_ref IS NOT NULL AND NEW.evidence_ref IS DISTINCT FROM OLD.evidence_ref THEN
                    RAISE EXCEPTION 'work order evidence is immutable' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.evidence_ref IS NOT NULL AND NEW.lifecycle_state <> 'completed' THEN
                    RAISE EXCEPTION 'work evidence exists only on completed work orders' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state NOT IN ('requested','approved','cancelled') THEN
                    RAISE EXCEPTION 'invalid work order transition' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state NOT IN ('approved','in_progress','cancelled') THEN
                    RAISE EXCEPTION 'invalid work order transition' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'in_progress' AND NEW.lifecycle_state NOT IN ('in_progress','completed','cancelled') THEN
                    RAISE EXCEPTION 'invalid work order transition' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'approved' AND NEW.approved_by IS NULL THEN
                    RAISE EXCEPTION 'approved work order requires approver' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'completed' AND NEW.evidence_ref IS NULL THEN
                    RAISE EXCEPTION 'completed work order requires evidence' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION asset_disposals_temporal_guard() RETURNS trigger AS $fn$
            DECLARE acquired_date date;
            BEGIN
                SELECT acquired_on INTO acquired_date FROM assets WHERE id = NEW.asset_id;
                IF acquired_date IS NULL OR NEW.disposed_on < acquired_date THEN
                    RAISE EXCEPTION 'asset disposal cannot precede asset acquisition' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('DROP TRIGGER IF EXISTS asset_disposals_temporal_guard_trigger ON asset_disposals');
        DB::statement('CREATE TRIGGER asset_disposals_temporal_guard_trigger BEFORE INSERT ON asset_disposals FOR EACH ROW EXECUTE FUNCTION asset_disposals_temporal_guard()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assets_lifecycle_guard() RETURNS trigger AS $fn$
            BEGIN
                IF NEW.lifecycle_state IS DISTINCT FROM OLD.lifecycle_state THEN
                    IF OLD.lifecycle_state <> 'in_service' OR NEW.lifecycle_state <> 'disposed' THEN
                        RAISE EXCEPTION 'asset lifecycle may only move in_service -> disposed' USING ERRCODE = 'check_violation';
                    END IF;
                    IF NOT EXISTS (SELECT 1 FROM asset_disposals WHERE asset_id = NEW.id) THEN
                        RAISE EXCEPTION 'an asset can become disposed only after an immutable disposal fact exists' USING ERRCODE = 'check_violation';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('DROP TRIGGER IF EXISTS assets_lifecycle_guard_trigger ON assets');
        DB::statement('CREATE TRIGGER assets_lifecycle_guard_trigger BEFORE UPDATE ON assets FOR EACH ROW EXECUTE FUNCTION assets_lifecycle_guard()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS assets_lifecycle_guard_trigger ON assets');
        DB::statement('DROP FUNCTION IF EXISTS assets_lifecycle_guard()');
        DB::statement('DROP TRIGGER IF EXISTS asset_disposals_temporal_guard_trigger ON asset_disposals');
        DB::statement('DROP FUNCTION IF EXISTS asset_disposals_temporal_guard()');
        DB::statement('DROP TRIGGER IF EXISTS work_orders_history_guard_trigger ON work_orders');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION work_orders_history_guard() RETURNS trigger AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'work order history cannot be deleted' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state IN ('completed','cancelled') THEN
                    RAISE EXCEPTION 'completed or cancelled work orders are retained history' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.organization_id IS DISTINCT FROM OLD.organization_id
                    OR NEW.originating_branch_id IS DISTINCT FROM OLD.originating_branch_id
                    OR NEW.facility_note IS DISTINCT FROM OLD.facility_note
                    OR NEW.description IS DISTINCT FROM OLD.description
                    OR NEW.requested_by IS DISTINCT FROM OLD.requested_by
                THEN
                    RAISE EXCEPTION 'work order request facts are immutable' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state NOT IN ('requested','approved','cancelled') THEN
                    RAISE EXCEPTION 'invalid work order transition' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state NOT IN ('approved','in_progress','cancelled') THEN
                    RAISE EXCEPTION 'invalid work order transition' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'in_progress' AND NEW.lifecycle_state NOT IN ('in_progress','completed','cancelled') THEN
                    RAISE EXCEPTION 'invalid work order transition' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'approved' AND NEW.approved_by IS NULL THEN
                    RAISE EXCEPTION 'approved work order requires approver' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'completed' AND NEW.evidence_ref IS NULL THEN
                    RAISE EXCEPTION 'completed work order requires evidence' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('CREATE TRIGGER work_orders_history_guard_trigger BEFORE UPDATE OR DELETE ON work_orders FOR EACH ROW EXECUTE FUNCTION work_orders_history_guard()');
    }
};
