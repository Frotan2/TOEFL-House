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
    asset_branch_id text;
    custodian_branch_id text;
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

    SELECT acquired_on, originating_branch_id
      INTO asset_acquired_on, asset_branch_id
      FROM assets
     WHERE id = NEW.asset_id;
    IF asset_acquired_on IS NULL OR NEW.assigned_on < asset_acquired_on THEN
        RAISE EXCEPTION 'custody cannot precede asset acquisition';
    END IF;

    -- Branch provenance is checked when a custody fact is created. A later
    -- person home-branch transfer must not make it impossible to close an
    -- already-recorded custody history.
    IF TG_OP = 'INSERT' THEN
        SELECT home_branch_id
          INTO custodian_branch_id
          FROM people
         WHERE id = NEW.custodian_person_id;
        IF custodian_branch_id IS NULL OR custodian_branch_id IS DISTINCT FROM asset_branch_id THEN
            RAISE EXCEPTION 'custody custodian must belong to the asset branch' USING ERRCODE = 'check_violation';
        END IF;
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
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS custody_history_guard ON custodies;
CREATE TRIGGER custody_history_guard
BEFORE INSERT OR UPDATE OR DELETE ON custodies
FOR EACH ROW EXECUTE FUNCTION resource_custody_history_guard();
SQL);
    }
};
