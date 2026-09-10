<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE book_issuances ADD CONSTRAINT book_issuances_return_date_check CHECK (returned_on IS NULL OR returned_on >= issued_on)');
        DB::statement("ALTER TABLE book_issuances ADD CONSTRAINT book_issuances_terminal_fact_check CHECK ((lifecycle_state = 'issued' AND returned_on IS NULL AND loss_evidence IS NULL) OR (lifecycle_state = 'returned' AND returned_on IS NOT NULL AND loss_evidence IS NULL) OR (lifecycle_state = 'lost' AND returned_on IS NULL AND loss_evidence IS NOT NULL))");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION book_issuances_history_guard() RETURNS trigger AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'book issuance history cannot be deleted' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state IN ('returned','lost') THEN
                    RAISE EXCEPTION 'returned or lost issuances are retained history' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.copy_id IS DISTINCT FROM OLD.copy_id
                    OR NEW.borrower_person_id IS DISTINCT FROM OLD.borrower_person_id
                    OR NEW.issued_on IS DISTINCT FROM OLD.issued_on
                    OR NEW.due_on IS DISTINCT FROM OLD.due_on
                    OR NEW.issued_by IS DISTINCT FROM OLD.issued_by
                THEN
                    RAISE EXCEPTION 'issued-book history fields are immutable' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'issued' AND NEW.lifecycle_state = 'issued'
                    AND (NEW.returned_on IS DISTINCT FROM OLD.returned_on OR NEW.loss_evidence IS DISTINCT FROM OLD.loss_evidence)
                THEN
                    RAISE EXCEPTION 'an open issuance may only close' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'issued' AND NEW.lifecycle_state = 'returned' AND NEW.returned_on IS NULL THEN
                    RAISE EXCEPTION 'a returned issuance requires returned_on' USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'issued' AND NEW.lifecycle_state = 'lost' AND NEW.loss_evidence IS NULL THEN
                    RAISE EXCEPTION 'a lost issuance requires loss evidence' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('DROP TRIGGER IF EXISTS book_issuances_terminal_immutable_trigger ON book_issuances');
        DB::statement('DROP FUNCTION IF EXISTS book_issuances_terminal_immutable()');
        DB::statement('CREATE TRIGGER book_issuances_history_guard_trigger BEFORE UPDATE OR DELETE ON book_issuances FOR EACH ROW EXECUTE FUNCTION book_issuances_history_guard()');

        DB::statement('ALTER TABLE custodies ADD CONSTRAINT custodies_release_date_check CHECK (released_on IS NULL OR released_on >= assigned_on)');

        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_completion_evidence_check CHECK (lifecycle_state <> 'completed' OR evidence_ref IS NOT NULL)");
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_approval_actor_check CHECK (lifecycle_state = 'requested' OR approved_by IS NOT NULL)");
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
        DB::statement('DROP TRIGGER IF EXISTS work_orders_terminal_immutable_trigger ON work_orders');
        DB::statement('DROP FUNCTION IF EXISTS work_orders_terminal_immutable()');
        DB::statement('CREATE TRIGGER work_orders_history_guard_trigger BEFORE UPDATE OR DELETE ON work_orders FOR EACH ROW EXECUTE FUNCTION work_orders_history_guard()');

        DB::statement('ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_request_independent_approvers_check CHECK ((approver_one_id IS NULL OR trim(approver_one_id) <> trim(requested_by)) AND (approver_two_id IS NULL OR trim(approver_two_id) <> trim(requested_by)))');
        DB::statement("ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_request_executor_check CHECK (lifecycle_state <> 'completed' OR trim(executed_by) = trim(requested_by))");
        DB::statement("CREATE UNIQUE INDEX asset_disposal_requests_one_active_per_asset ON asset_disposal_requests (asset_id) WHERE lifecycle_state IN ('requested','approved')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS asset_disposal_requests_one_active_per_asset');
        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT IF EXISTS asset_disposal_request_executor_check');
        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT IF EXISTS asset_disposal_request_independent_approvers_check');

        DB::statement('DROP TRIGGER IF EXISTS work_orders_history_guard_trigger ON work_orders');
        DB::statement('DROP FUNCTION IF EXISTS work_orders_history_guard()');
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_approval_actor_check');
        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS work_orders_completion_evidence_check');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION work_orders_terminal_immutable() RETURNS trigger AS $fn$
            BEGIN
                IF OLD.lifecycle_state IN ('completed','cancelled') THEN
                    RAISE EXCEPTION 'completed or cancelled work orders are retained history';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('CREATE TRIGGER work_orders_terminal_immutable_trigger BEFORE UPDATE OR DELETE ON work_orders FOR EACH ROW EXECUTE FUNCTION work_orders_terminal_immutable()');

        DB::statement('ALTER TABLE custodies DROP CONSTRAINT IF EXISTS custodies_release_date_check');

        DB::statement('DROP TRIGGER IF EXISTS book_issuances_history_guard_trigger ON book_issuances');
        DB::statement('DROP FUNCTION IF EXISTS book_issuances_history_guard()');
        DB::statement('ALTER TABLE book_issuances DROP CONSTRAINT IF EXISTS book_issuances_terminal_fact_check');
        DB::statement('ALTER TABLE book_issuances DROP CONSTRAINT IF EXISTS book_issuances_return_date_check');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION book_issuances_terminal_immutable() RETURNS trigger AS $fn$
            BEGIN
                IF OLD.lifecycle_state IN ('returned','lost') THEN
                    RAISE EXCEPTION 'returned or lost issuances are retained custody history';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
        DB::statement('CREATE TRIGGER book_issuances_terminal_immutable_trigger BEFORE UPDATE OR DELETE ON book_issuances FOR EACH ROW EXECUTE FUNCTION book_issuances_terminal_immutable()');
    }
};
