<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION resource_custody_history_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    asset_acquired_on date;
    previous_assigned_on date;
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'custody history cannot be deleted' USING ERRCODE = 'check_violation';
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF OLD.asset_id IS DISTINCT FROM NEW.asset_id
           OR OLD.custodian_person_id IS DISTINCT FROM NEW.custodian_person_id
           OR OLD.assigned_on IS DISTINCT FROM NEW.assigned_on
           OR OLD.assigned_by IS DISTINCT FROM NEW.assigned_by THEN
            RAISE EXCEPTION 'custody identity and assignment provenance are immutable';
        END IF;
        IF OLD.released_on IS NOT NULL AND NEW.released_on IS DISTINCT FROM OLD.released_on THEN
            RAISE EXCEPTION 'closed custody history is immutable';
        END IF;
        IF OLD.released_on IS NULL AND NEW.released_on IS NOT NULL AND NEW.released_on < OLD.assigned_on THEN
            RAISE EXCEPTION 'custody release cannot precede assignment';
        END IF;
        IF OLD.released_on IS NULL AND NEW.released_on IS NULL THEN
            RAISE EXCEPTION 'open custody may only be closed';
        END IF;
    END IF;

    SELECT acquired_on INTO asset_acquired_on FROM assets WHERE id = NEW.asset_id;
    IF asset_acquired_on IS NULL OR NEW.assigned_on < asset_acquired_on THEN
        RAISE EXCEPTION 'custody cannot precede asset acquisition';
    END IF;
    IF NEW.released_on IS NOT NULL AND NEW.released_on < NEW.assigned_on THEN
        RAISE EXCEPTION 'custody release cannot precede assignment';
    END IF;

    SELECT MAX(c.assigned_on)
      INTO previous_assigned_on
      FROM custodies c
     WHERE c.asset_id = NEW.asset_id
       AND c.id <> NEW.id;
    IF previous_assigned_on IS NOT NULL AND NEW.assigned_on < previous_assigned_on THEN
        RAISE EXCEPTION 'custody history cannot move backward in time';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS custody_history_guard ON custodies;
CREATE TRIGGER custody_history_guard
BEFORE INSERT OR UPDATE OR DELETE ON custodies
FOR EACH ROW EXECUTE FUNCTION resource_custody_history_guard();

CREATE OR REPLACE FUNCTION resource_disposal_request_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'disposal request history cannot be deleted' USING ERRCODE = 'check_violation';
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.requested_by IS DISTINCT FROM NEW.requested_by
           OR OLD.method IS DISTINCT FROM NEW.method
           OR OLD.reason IS DISTINCT FROM NEW.reason THEN
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
    ELSE
        RAISE EXCEPTION 'unknown disposal request lifecycle state %', NEW.lifecycle_state;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS asset_disposal_request_guard ON asset_disposal_requests;
CREATE TRIGGER asset_disposal_request_guard
BEFORE INSERT OR UPDATE OR DELETE ON asset_disposal_requests
FOR EACH ROW EXECUTE FUNCTION resource_disposal_request_guard();

CREATE OR REPLACE FUNCTION resource_asset_disposal_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'asset disposal facts cannot be deleted' USING ERRCODE = 'check_violation';
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF OLD.asset_id IS DISTINCT FROM NEW.asset_id
           OR OLD.method IS DISTINCT FROM NEW.method
           OR OLD.reason IS DISTINCT FROM NEW.reason
           OR OLD.disposed_on IS DISTINCT FROM NEW.disposed_on
           OR OLD.requested_by IS DISTINCT FROM NEW.requested_by
           OR OLD.approver_one IS DISTINCT FROM NEW.approver_one
           OR OLD.approver_two IS DISTINCT FROM NEW.approver_two THEN
            RAISE EXCEPTION 'asset disposal facts and provenance are immutable' USING ERRCODE = 'check_violation';
        END IF;
    END IF;

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
BEFORE INSERT OR UPDATE OR DELETE ON asset_disposals
FOR EACH ROW EXECUTE FUNCTION resource_asset_disposal_guard();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS custody_history_guard ON custodies;
DROP FUNCTION IF EXISTS resource_custody_history_guard();
DROP TRIGGER IF EXISTS asset_disposal_request_guard ON asset_disposal_requests;
DROP FUNCTION IF EXISTS resource_disposal_request_guard();
DROP TRIGGER IF EXISTS asset_disposal_guard ON asset_disposals;
DROP FUNCTION IF EXISTS resource_asset_disposal_guard();
SQL);
    }
};
