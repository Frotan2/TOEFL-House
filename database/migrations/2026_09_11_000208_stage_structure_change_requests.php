<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ORG-OPS-01: staged governance for organization topology changes.
 *
 * Structure changes are governed by the four-actor StructureDecision chain
 * (initiator, reviewer, two distinct owners). That chain cannot execute in a
 * single operator session, so it is staged exactly like organization-wide
 * grants (000116):
 *
 *   - an initiator (organization.structure.initiate) proposes the change; the
 *     request is born 'proposed' and no topology fact is written;
 *   - a distinct reviewer (organization.structure.review) signs the review;
 *   - two distinct owners (organization.structure.approve), each different
 *     from the initiator and reviewer, sign in their own sessions; the second
 *     owner signature executes the canonical CreateStructureUnit /
 *     RenameStructureUnit / TransitionStructureUnit / TransferBranchToCampus
 *     command in the same owning transaction — the request moves to 'executed';
 *   - a reviewer or owner may reject (terminal, auditable, no fact written),
 *     and the initiator may withdraw a proposal that has no signature yet.
 *
 * The database trigger is the concurrency-safe boundary: legal transitions,
 * write-once signature slots, immutable change payload, and a hard delete ban.
 * A partial unique index prevents two open requests for the same change
 * (duplicate topology / concurrent duplicate proposals).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE structure_change_requests (
                id character(36) PRIMARY KEY,
                change_type character varying NOT NULL,
                unit_type character varying NOT NULL,
                organization_id character(36) REFERENCES organizations (id),
                target_id character(36),
                parent_scope_type character varying,
                parent_scope_id character(36),
                change_key character varying NOT NULL,
                payload jsonb NOT NULL,
                lifecycle_state character varying NOT NULL DEFAULT 'proposed',
                proposed_by character(36) NOT NULL,
                reviewed_by character(36),
                owner_one_id character(36),
                owner_two_id character(36),
                closed_by character(36),
                closure_reason character varying,
                result jsonb,
                created_at timestamptz NOT NULL,
                updated_at timestamptz NOT NULL
            )
            SQL);
        DB::statement("ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_change_type_check CHECK (change_type IN ('create_organization','create_campus','create_branch','create_department','rename_unit','transition_unit','transfer_branch'))");
        DB::statement("ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_unit_type_check CHECK (unit_type IN ('organization','campus','branch','department'))");
        DB::statement("ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_parent_scope_type_check CHECK (parent_scope_type IS NULL OR parent_scope_type IN ('organization','campus','branch'))");
        DB::statement("ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_state_check CHECK (lifecycle_state IN ('proposed','reviewed','executed','rejected','withdrawn'))");
        DB::statement('ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_change_key_check CHECK (char_length(change_key) > 0)');
        DB::statement('ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_proposed_by_check CHECK (char_length(proposed_by) > 0)');
        DB::statement('ALTER TABLE structure_change_requests ADD CONSTRAINT structure_change_requests_payload_check CHECK (payload IS NOT NULL)');

        // At most one OPEN proposal per deterministic change identity, so two
        // concurrent initiators cannot stage duplicate topology; executed and
        // closed history is exempt, so the same lifecycle action can recur.
        DB::statement('CREATE UNIQUE INDEX structure_changes_one_open_per_key ON structure_change_requests (change_key) WHERE lifecycle_state IN (\'proposed\', \'reviewed\')');
        DB::statement('CREATE INDEX structure_change_requests_organization_index ON structure_change_requests (organization_id)');
        DB::statement('CREATE INDEX structure_change_requests_state_index ON structure_change_requests (lifecycle_state)');
        DB::statement('CREATE INDEX structure_change_requests_target_index ON structure_change_requests (unit_type, target_id)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION structure_change_requests_guard() RETURNS trigger AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'structure change requests are auditable governance facts and cannot be deleted'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.lifecycle_state <> 'proposed' THEN
                        RAISE EXCEPTION 'a structure change request is born proposed; only review, approval, rejection, withdrawal and execution change it (state: %)', NEW.lifecycle_state
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.reviewed_by IS NOT NULL OR NEW.owner_one_id IS NOT NULL OR NEW.owner_two_id IS NOT NULL
                        OR NEW.closed_by IS NOT NULL OR NEW.closure_reason IS NOT NULL OR NEW.result IS NOT NULL
                    THEN
                        RAISE EXCEPTION 'a proposed structure change request carries no signatures, closure or result'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    RETURN NEW;
                END IF;

                -- UPDATE
                IF OLD.lifecycle_state IN ('executed', 'rejected', 'withdrawn') THEN
                    RAISE EXCEPTION 'a closed structure change request (state: %) cannot change', OLD.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.change_type IS DISTINCT FROM OLD.change_type
                    OR NEW.unit_type IS DISTINCT FROM OLD.unit_type
                    OR NEW.organization_id IS DISTINCT FROM OLD.organization_id
                    OR NEW.target_id IS DISTINCT FROM OLD.target_id
                    OR NEW.parent_scope_type IS DISTINCT FROM OLD.parent_scope_type
                    OR NEW.parent_scope_id IS DISTINCT FROM OLD.parent_scope_id
                    OR NEW.change_key IS DISTINCT FROM OLD.change_key
                    OR NEW.payload IS DISTINCT FROM OLD.payload
                    OR NEW.proposed_by IS DISTINCT FROM OLD.proposed_by
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION 'only lifecycle state, signatures, closure and result may change on a structure change request'
                        USING ERRCODE = 'check_violation';
                END IF;

                -- Signature slots are written once.
                IF NEW.reviewed_by IS DISTINCT FROM OLD.reviewed_by AND OLD.reviewed_by IS NOT NULL THEN
                    RAISE EXCEPTION 'the reviewer slot on a structure change request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.owner_one_id IS DISTINCT FROM OLD.owner_one_id AND OLD.owner_one_id IS NOT NULL THEN
                    RAISE EXCEPTION 'the first owner slot on a structure change request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.owner_two_id IS DISTINCT FROM OLD.owner_two_id AND OLD.owner_two_id IS NOT NULL THEN
                    RAISE EXCEPTION 'the second owner slot on a structure change request is written once'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.lifecycle_state = 'proposed' AND NEW.lifecycle_state = 'reviewed' THEN
                    IF NEW.reviewed_by IS NULL OR trim(NEW.reviewed_by) = trim(OLD.proposed_by) THEN
                        RAISE EXCEPTION 'a reviewed structure change request needs a reviewer distinct from the initiator'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.owner_one_id IS NOT NULL OR NEW.owner_two_id IS NOT NULL OR NEW.closed_by IS NOT NULL OR NEW.result IS NOT NULL THEN
                        RAISE EXCEPTION 'review records only the reviewer signature'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'proposed' AND NEW.lifecycle_state IN ('rejected', 'withdrawn') THEN
                    IF NEW.closed_by IS NULL THEN
                        RAISE EXCEPTION 'closing a proposed structure change request records the closing actor'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'reviewed' AND NEW.lifecycle_state = 'rejected' THEN
                    IF NEW.closed_by IS NULL OR NEW.owner_one_id IS NOT NULL OR NEW.result IS NOT NULL THEN
                        RAISE EXCEPTION 'an owner rejection records only the closing actor'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF OLD.lifecycle_state = 'reviewed' AND NEW.lifecycle_state = 'executed' THEN
                    IF NEW.owner_one_id IS NULL OR NEW.owner_two_id IS NULL OR NEW.result IS NULL THEN
                        RAISE EXCEPTION 'an executed structure change request carries two owner signatures and the command result'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF trim(NEW.owner_one_id) = trim(NEW.owner_two_id)
                        OR trim(NEW.owner_one_id) = trim(OLD.proposed_by)
                        OR trim(NEW.owner_two_id) = trim(OLD.proposed_by)
                        OR trim(NEW.owner_one_id) = trim(OLD.reviewed_by)
                        OR trim(NEW.owner_two_id) = trim(OLD.reviewed_by)
                    THEN
                        RAISE EXCEPTION 'structure execution needs two distinct owners, separate from the initiator and reviewer'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    -- The first owner signature may arrive in the same UPDATE
                    -- that advances a reviewed request only when it does not
                    -- also try to execute; execution needs both slots filled.
                ELSIF OLD.lifecycle_state = 'reviewed' AND NEW.lifecycle_state = 'reviewed' THEN
                    -- First owner signature while staying reviewed.
                    IF OLD.owner_one_id IS NOT NULL OR NEW.owner_one_id IS NULL OR NEW.owner_two_id IS NOT NULL THEN
                        RAISE EXCEPTION 'a reviewed structure change request accepts only its first owner signature'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSE
                    RAISE EXCEPTION 'a structure change request moves only proposed -> reviewed -> executed, or to rejected/withdrawn (state: % -> %)',
                        OLD.lifecycle_state, NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER structure_change_requests_guard_trigger BEFORE INSERT OR UPDATE OR DELETE ON structure_change_requests FOR EACH ROW EXECUTE FUNCTION structure_change_requests_guard()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS structure_change_requests_guard_trigger ON structure_change_requests');
        DB::statement('DROP FUNCTION IF EXISTS structure_change_requests_guard()');
        DB::statement('DROP INDEX IF EXISTS structure_change_requests_target_index');
        DB::statement('DROP INDEX IF EXISTS structure_change_requests_state_index');
        DB::statement('DROP INDEX IF EXISTS structure_change_requests_organization_index');
        DB::statement('DROP INDEX IF EXISTS structure_changes_one_open_per_key');
        DB::statement('DROP TABLE IF EXISTS structure_change_requests');
    }
};
