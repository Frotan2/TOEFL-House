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
BEFORE INSERT OR UPDATE ON custodies
FOR EACH ROW EXECUTE FUNCTION resource_custody_history_guard();

CREATE OR REPLACE FUNCTION resource_issuance_history_guard()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF OLD.copy_id IS DISTINCT FROM NEW.copy_id
           OR OLD.borrower_person_id IS DISTINCT FROM NEW.borrower_person_id
           OR OLD.issued_on IS DISTINCT FROM NEW.issued_on
           OR OLD.due_on IS DISTINCT FROM NEW.due_on
           OR OLD.issued_by IS DISTINCT FROM NEW.issued_by THEN
            RAISE EXCEPTION 'book issuance identity and issue provenance are immutable';
        END IF;
        IF OLD.lifecycle_state IN ('returned', 'lost') THEN
            RAISE EXCEPTION 'terminal book issuance history is immutable';
        END IF;
        IF OLD.lifecycle_state = 'issued' AND NEW.lifecycle_state NOT IN ('issued', 'returned', 'lost') THEN
            RAISE EXCEPTION 'invalid book issuance lifecycle transition';
        END IF;
    ELSE
        IF NEW.lifecycle_state <> 'issued' THEN
            RAISE EXCEPTION 'new book issuances must start in issued state';
        END IF;
    END IF;

    IF NEW.issued_on IS NULL OR NEW.due_on IS NULL OR NEW.due_on < NEW.issued_on THEN
        RAISE EXCEPTION 'book issuance due date cannot precede issue date';
    END IF;

    IF NEW.lifecycle_state = 'issued' THEN
        IF NEW.returned_on IS NOT NULL OR NULLIF(BTRIM(COALESCE(NEW.loss_evidence, '')), '') IS NOT NULL THEN
            RAISE EXCEPTION 'open book issuance cannot contain terminal evidence';
        END IF;
    ELSIF NEW.lifecycle_state = 'returned' THEN
        IF NEW.returned_on IS NULL OR NEW.returned_on < NEW.issued_on OR NULLIF(BTRIM(COALESCE(NEW.loss_evidence, '')), '') IS NOT NULL THEN
            RAISE EXCEPTION 'returned issuance requires a valid return date and no loss evidence';
        END IF;
    ELSIF NEW.lifecycle_state = 'lost' THEN
        IF NULLIF(BTRIM(COALESCE(NEW.loss_evidence, '')), '') IS NULL OR NEW.returned_on IS NOT NULL THEN
            RAISE EXCEPTION 'lost issuance requires loss evidence and no return date';
        END IF;
    ELSE
        RAISE EXCEPTION 'unknown book issuance lifecycle state %', NEW.lifecycle_state;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS issuance_history_guard ON book_issuances;
CREATE TRIGGER issuance_history_guard
BEFORE INSERT OR UPDATE ON book_issuances
FOR EACH ROW EXECUTE FUNCTION resource_issuance_history_guard();
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS issuance_history_guard ON book_issuances;
DROP FUNCTION IF EXISTS resource_issuance_history_guard();
DROP TRIGGER IF EXISTS custody_history_guard ON custodies;
DROP FUNCTION IF EXISTS resource_custody_history_guard();
SQL);
    }
};
