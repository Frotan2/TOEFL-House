<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION resource_disposal_request_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF NEW.requested_by IS DISTINCT FROM OLD.requested_by
           OR NEW.method IS DISTINCT FROM OLD.method
           OR NEW.reason IS DISTINCT FROM OLD.reason THEN
            RAISE EXCEPTION 'disposal request facts are immutable';
        END IF;

        IF OLD.approver_one_id IS NOT NULL AND NEW.approver_one_id IS DISTINCT FROM OLD.approver_one_id THEN
            RAISE EXCEPTION 'first disposal approver is immutable';
        END IF;
        IF OLD.approver_two_id IS NOT NULL AND NEW.approver_two_id IS DISTINCT FROM OLD.approver_two_id THEN
            RAISE EXCEPTION 'second disposal approver is immutable';
        END IF;

        IF OLD.executed_by IS NOT NULL AND NEW.executed_by IS DISTINCT FROM OLD.executed_by THEN
            RAISE EXCEPTION 'disposal executor is immutable';
        END IF;
        IF OLD.disposal_id IS NOT NULL AND NEW.disposal_id IS DISTINCT FROM OLD.disposal_id THEN
            RAISE EXCEPTION 'disposal linkage is immutable';
        END IF;

        IF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state NOT IN ('requested', 'approved') THEN
            RAISE EXCEPTION 'invalid disposal request transition from requested to %', NEW.lifecycle_state;
        END IF;
        IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state NOT IN ('approved', 'completed') THEN
            RAISE EXCEPTION 'invalid disposal request transition from approved to %', NEW.lifecycle_state;
        END IF;
        IF OLD.lifecycle_state = 'completed' AND NEW.lifecycle_state <> 'completed' THEN
            RAISE EXCEPTION 'completed disposal request is immutable';
        END IF;
    ELSE
        IF NEW.lifecycle_state <> 'requested' THEN
            RAISE EXCEPTION 'new disposal requests must start in requested state';
        END IF;
    END IF;

    IF NEW.lifecycle_state = 'requested' THEN
        IF NEW.approver_two_id IS NOT NULL OR NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL THEN
            RAISE EXCEPTION 'requested disposal cannot contain terminal provenance';
        END IF;
    ELSIF NEW.lifecycle_state = 'approved' THEN
        IF NEW.requested_by IS NULL
           OR NEW.approver_one_id IS NULL
           OR NEW.approver_two_id IS NULL
           OR NEW.approver_one_id = NEW.approver_two_id
           OR NEW.requested_by IN (NEW.approver_one_id, NEW.approver_two_id)
           OR NEW.executed_by IS NOT NULL
           OR NEW.disposal_id IS NOT NULL THEN
            RAISE EXCEPTION 'approved disposal requires two distinct independent approvers and no execution provenance';
        END IF;
    ELSIF NEW.lifecycle_state = 'completed' THEN
        IF NEW.requested_by IS NULL
           OR NEW.approver_one_id IS NULL
           OR NEW.approver_two_id IS NULL
           OR NEW.approver_one_id = NEW.approver_two_id
           OR NEW.requested_by IN (NEW.approver_one_id, NEW.approver_two_id)
           OR NEW.executed_by IS NULL
           OR NEW.executed_by IS DISTINCT FROM NEW.requested_by
           OR NEW.disposal_id IS NULL THEN
            RAISE EXCEPTION 'completed disposal requires approved provenance, request linkage and the requesting executor';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS asset_disposal_request_guard ON asset_disposal_requests;
CREATE TRIGGER asset_disposal_request_guard
BEFORE INSERT OR UPDATE ON asset_disposal_requests
FOR EACH ROW EXECUTE FUNCTION resource_disposal_request_guard();

CREATE OR REPLACE FUNCTION resource_asset_disposal_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM asset_disposals d
        WHERE d.asset_id = NEW.asset_id
          AND d.id <> NEW.id
    ) THEN
        RAISE EXCEPTION 'an asset can have only one disposal fact';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM assets a
        WHERE a.id = NEW.asset_id
          AND NEW.disposed_on < a.acquired_on
    ) THEN
        RAISE EXCEPTION 'disposal cannot precede asset acquisition';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM custodies c
        WHERE c.asset_id = NEW.asset_id
          AND c.released_on IS NULL
          AND NEW.disposed_on < c.assigned_on
    ) THEN
        RAISE EXCEPTION 'disposal cannot precede the current custody assignment';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM asset_disposal_requests r
        WHERE r.asset_id = NEW.asset_id
          AND r.lifecycle_state = 'approved'
          AND r.method = NEW.method
          AND r.reason = NEW.reason
          AND r.requested_by = NEW.requested_by
          AND r.approver_one_id = NEW.approver_one
          AND r.approver_two_id = NEW.approver_two
    ) THEN
        RAISE EXCEPTION 'disposal requires a matching approved disposal request';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS asset_disposal_guard ON asset_disposals;
CREATE TRIGGER asset_disposal_guard
BEFORE INSERT OR UPDATE ON asset_disposals
FOR EACH ROW EXECUTE FUNCTION resource_asset_disposal_guard();

CREATE UNIQUE INDEX IF NOT EXISTS asset_disposals_asset_id_unique
    ON asset_disposals (asset_id);
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS asset_disposal_guard ON asset_disposals;
DROP FUNCTION IF EXISTS resource_asset_disposal_guard();
DROP INDEX IF EXISTS asset_disposals_asset_id_unique;
DROP TRIGGER IF EXISTS asset_disposal_request_guard ON asset_disposal_requests;
DROP FUNCTION IF EXISTS resource_disposal_request_guard();
SQL);
    }
};
