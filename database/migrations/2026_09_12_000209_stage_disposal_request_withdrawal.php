<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Staged disposal gains a requester-only withdrawal while the request is
     * still 'requested'. Without it an abandoned request wedges the asset
     * forever: the one-active-per-asset guard rejects every new request, both
     * history guards prohibit deletion, and no authority could remove the
     * stale row. Withdrawal is a terminal state; once two approvers have
     * signed ('approved') the request can only be executed.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE asset_disposal_requests ADD COLUMN withdrawn_by char(36) NULL');

        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT asset_disposal_requests_lifecycle_state_check');
        DB::statement(<<<'SQL'
            ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_requests_lifecycle_state_check
                CHECK (lifecycle_state IN ('requested', 'approved', 'completed', 'withdrawn'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_request_withdrawal_check
                CHECK (
                    (lifecycle_state <> 'withdrawn' OR (withdrawn_by IS NOT NULL AND trim(withdrawn_by) = trim(requested_by)))
                    AND (withdrawn_by IS NULL OR lifecycle_state = 'withdrawn')
                )
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION asset_disposal_requests_guard() RETURNS trigger AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'asset disposal requests are auditable facts and cannot be deleted'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.lifecycle_state <> 'requested' THEN
                        RAISE EXCEPTION 'an asset disposal request is born requested; only approval, execution and withdrawal change it (state: %)', NEW.lifecycle_state
                            USING ERRCODE = 'check_violation';
                    END IF;

                    IF NEW.approver_one_id IS NOT NULL OR NEW.approver_two_id IS NOT NULL
                        OR NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL
                        OR NEW.withdrawn_by IS NOT NULL
                    THEN
                        RAISE EXCEPTION 'an asset disposal request is born without approvers, executor, disposal or withdrawal'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    RETURN NEW;
                END IF;

                -- UPDATE
                IF OLD.lifecycle_state = 'completed' THEN
                    RAISE EXCEPTION 'a completed asset disposal request is closed'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.lifecycle_state = 'withdrawn' THEN
                    RAISE EXCEPTION 'a withdrawn asset disposal request is closed'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state = 'requested' THEN
                    -- The first signature: only the first approver slot may
                    -- be filled while the request stays requested.
                    IF OLD.approver_one_id IS NOT NULL OR NEW.approver_one_id IS NULL
                        OR NEW.approver_two_id IS NOT NULL OR NEW.withdrawn_by IS NOT NULL
                    THEN
                        RAISE EXCEPTION 'a requested asset disposal request accepts only its first approver signature'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state = 'withdrawn' THEN
                    -- Requester-only withdrawal before the request is fully approved.
                    IF NEW.withdrawn_by IS NULL THEN
                        RAISE EXCEPTION 'a withdrawn asset disposal request records the withdrawing requester'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF trim(NEW.withdrawn_by) <> trim(NEW.requested_by) THEN
                        RAISE EXCEPTION 'only the requesting session withdraws its own asset disposal request'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.approver_two_id IS NOT NULL OR NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL THEN
                        RAISE EXCEPTION 'a withdrawn asset disposal request carries no second approval or execution provenance'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state = 'approved' THEN
                    IF NEW.approver_one_id IS NULL OR NEW.approver_two_id IS NULL THEN
                        RAISE EXCEPTION 'an approved asset disposal request carries two distinct approvers'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF trim(NEW.approver_one_id) = trim(NEW.approver_two_id) THEN
                        RAISE EXCEPTION 'an asset disposal needs two distinct approvers'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL OR NEW.withdrawn_by IS NOT NULL THEN
                        RAISE EXCEPTION 'an approved asset disposal request is not yet executed'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state = 'completed' THEN
                    IF NEW.executed_by IS NULL OR NEW.disposal_id IS NULL THEN
                        RAISE EXCEPTION 'executing an asset disposal request records the executor and the disposal'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.withdrawn_by IS NOT NULL THEN
                        RAISE EXCEPTION 'a completed asset disposal request is not withdrawn'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSE
                    RAISE EXCEPTION 'an asset disposal request moves only requested -> approved -> completed, or requested -> withdrawn (state: % -> %)',
                        OLD.lifecycle_state, NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.approver_one_id IS DISTINCT FROM OLD.approver_one_id
                    AND OLD.approver_one_id IS NOT NULL
                THEN
                    RAISE EXCEPTION 'an approver slot on an asset disposal request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.approver_two_id IS DISTINCT FROM OLD.approver_two_id
                    AND OLD.approver_two_id IS NOT NULL
                THEN
                    RAISE EXCEPTION 'an approver slot on an asset disposal request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.withdrawn_by IS DISTINCT FROM OLD.withdrawn_by
                    AND OLD.withdrawn_by IS NOT NULL
                THEN
                    RAISE EXCEPTION 'the withdrawal on an asset disposal request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.asset_id IS DISTINCT FROM OLD.asset_id
                    OR NEW.method IS DISTINCT FROM OLD.method
                    OR NEW.reason IS DISTINCT FROM OLD.reason
                    OR NEW.requested_by IS DISTINCT FROM OLD.requested_by
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION 'only the lifecycle state, approver slots, executor, withdrawal and disposal may change on an asset disposal request'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;

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
                    IF OLD.withdrawn_by IS NOT NULL AND NEW.withdrawn_by IS DISTINCT FROM OLD.withdrawn_by THEN
                        RAISE EXCEPTION 'disposal withdrawal is immutable';
                    END IF;

                    IF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state NOT IN ('requested', 'approved', 'withdrawn') THEN
                        RAISE EXCEPTION 'invalid disposal request transition from requested to %', NEW.lifecycle_state;
                    END IF;
                    IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state NOT IN ('approved', 'completed') THEN
                        RAISE EXCEPTION 'invalid disposal request transition from approved to %', NEW.lifecycle_state;
                    END IF;
                    IF OLD.lifecycle_state = 'completed' AND NEW.lifecycle_state <> 'completed' THEN
                        RAISE EXCEPTION 'completed disposal request is immutable';
                    END IF;
                    IF OLD.lifecycle_state = 'withdrawn' AND NEW.lifecycle_state <> 'withdrawn' THEN
                        RAISE EXCEPTION 'withdrawn disposal request is immutable';
                    END IF;
                ELSE
                    IF NEW.lifecycle_state <> 'requested' THEN
                        RAISE EXCEPTION 'new disposal requests must start in requested state';
                    END IF;
                END IF;

                IF NEW.lifecycle_state = 'requested' THEN
                    IF NEW.approver_two_id IS NOT NULL OR NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL OR NEW.withdrawn_by IS NOT NULL THEN
                        RAISE EXCEPTION 'requested disposal cannot contain terminal provenance';
                    END IF;
                ELSIF NEW.lifecycle_state = 'approved' THEN
                    IF NEW.requested_by IS NULL
                       OR NEW.approver_one_id IS NULL
                       OR NEW.approver_two_id IS NULL
                       OR NEW.approver_one_id = NEW.approver_two_id
                       OR NEW.requested_by IN (NEW.approver_one_id, NEW.approver_two_id)
                       OR NEW.executed_by IS NOT NULL
                       OR NEW.disposal_id IS NOT NULL
                       OR NEW.withdrawn_by IS NOT NULL THEN
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
                       OR NEW.disposal_id IS NULL
                       OR NEW.withdrawn_by IS NOT NULL THEN
                        RAISE EXCEPTION 'completed disposal requires approved provenance, request linkage and the requesting executor';
                    END IF;
                ELSIF NEW.lifecycle_state = 'withdrawn' THEN
                    IF NEW.requested_by IS NULL
                       OR NEW.withdrawn_by IS NULL
                       OR NEW.withdrawn_by IS DISTINCT FROM NEW.requested_by
                       OR NEW.approver_two_id IS NOT NULL
                       OR NEW.executed_by IS NOT NULL
                       OR NEW.disposal_id IS NOT NULL THEN
                        RAISE EXCEPTION 'withdrawn disposal requires the requesting withdrawer and no second approval or execution provenance';
                    END IF;
                ELSE
                    RAISE EXCEPTION 'unknown disposal request lifecycle state %', NEW.lifecycle_state;
                END IF;

                RETURN NEW;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        // Restoring the three-state check fails loudly while withdrawn rows
        // exist; recorded withdrawals are facts and are never rewritten.
        DB::unprepared(<<<'SQL'
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

            CREATE OR REPLACE FUNCTION asset_disposal_requests_guard() RETURNS trigger AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'asset disposal requests are auditable facts and cannot be deleted'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.lifecycle_state <> 'requested' THEN
                        RAISE EXCEPTION 'an asset disposal request is born requested; only approval and execution change it (state: %)', NEW.lifecycle_state
                            USING ERRCODE = 'check_violation';
                    END IF;

                    IF NEW.approver_one_id IS NOT NULL OR NEW.approver_two_id IS NOT NULL
                        OR NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL
                    THEN
                        RAISE EXCEPTION 'an asset disposal request is born without approvers, executor or disposal'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    RETURN NEW;
                END IF;

                -- UPDATE
                IF OLD.lifecycle_state = 'completed' THEN
                    RAISE EXCEPTION 'a completed asset disposal request is closed'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state = 'requested' THEN
                    -- The first signature: only the first approver slot may
                    -- be filled while the request stays requested.
                    IF OLD.approver_one_id IS NOT NULL OR NEW.approver_one_id IS NULL
                        OR NEW.approver_two_id IS NOT NULL
                    THEN
                        RAISE EXCEPTION 'a requested asset disposal request accepts only its first approver signature'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'requested' AND NEW.lifecycle_state = 'approved' THEN
                    IF NEW.approver_one_id IS NULL OR NEW.approver_two_id IS NULL THEN
                        RAISE EXCEPTION 'an approved asset disposal request carries two distinct approvers'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF trim(NEW.approver_one_id) = trim(NEW.approver_two_id) THEN
                        RAISE EXCEPTION 'an asset disposal needs two distinct approvers'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.executed_by IS NOT NULL OR NEW.disposal_id IS NOT NULL THEN
                        RAISE EXCEPTION 'an approved asset disposal request is not yet executed'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state = 'completed' THEN
                    IF NEW.executed_by IS NULL OR NEW.disposal_id IS NULL THEN
                        RAISE EXCEPTION 'executing an asset disposal request records the executor and the disposal'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSE
                    RAISE EXCEPTION 'an asset disposal request moves only requested -> approved -> completed (state: % -> %)',
                        OLD.lifecycle_state, NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.approver_one_id IS DISTINCT FROM OLD.approver_one_id
                    AND OLD.approver_one_id IS NOT NULL
                THEN
                    RAISE EXCEPTION 'an approver slot on an asset disposal request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.approver_two_id IS DISTINCT FROM OLD.approver_two_id
                    AND OLD.approver_two_id IS NOT NULL
                THEN
                    RAISE EXCEPTION 'an approver slot on an asset disposal request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.asset_id IS DISTINCT FROM OLD.asset_id
                    OR NEW.method IS DISTINCT FROM OLD.method
                    OR NEW.reason IS DISTINCT FROM OLD.reason
                    OR NEW.requested_by IS DISTINCT FROM OLD.requested_by
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION 'only the lifecycle state, approver slots, executor and disposal may change on an asset disposal request'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);

        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT IF EXISTS asset_disposal_request_withdrawal_check');
        DB::statement('ALTER TABLE asset_disposal_requests DROP CONSTRAINT asset_disposal_requests_lifecycle_state_check');
        DB::statement(<<<'SQL'
            ALTER TABLE asset_disposal_requests ADD CONSTRAINT asset_disposal_requests_lifecycle_state_check
                CHECK (lifecycle_state IN ('requested', 'approved', 'completed'))
            SQL);
        DB::statement('ALTER TABLE asset_disposal_requests DROP COLUMN withdrawn_by');
    }
};
