<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Durable consent-lifecycle boundary.
 *
 * `consents` is the authoritative record of a subject's permission to use
 * their personal data for one purpose. Until now its legality lived only in
 * `ConsentLifecycle` and `TransitionConsent`: the schema constrained the
 * *vocabulary* (consents_lifecycle_state_check), the window
 * (consents_period_check) and one open consent per subject+purpose
 * (consents_one_open_per_subject_purpose), but any writer could still insert
 * a forged `active` consent or rewrite a recorded one, which is exactly what
 * append-only privacy evidence must not allow. This guard closes that gap at
 * the database boundary, matching the house pattern already used for
 * `privacy_export_requests` (000114), `people` identity finality and the
 * Documents evidence guards (000206/000210):
 *
 *   - INSERT: a consent is born `draft` with a non-empty evidence reference.
 *     Verification and activation are separate, attributable acts;
 *   - UPDATE: only `lifecycle_state` (and the Eloquent `updated_at` touch)
 *     may change, and only along the canonical forward chain
 *     draft -> submitted -> verified -> active -> expired|revoked -> archived.
 *     The subject, purpose, evidence locator, effective window and recorder
 *     are write-once: a corrected consent is a new consent, never a rewrite;
 *   - DELETE: never. Consent evidence is an auditable fact; ending use is a
 *     revocation, an expiry or an archive, all of which retain the record.
 *
 * The PHP transition table stays the single business authority for what a
 * transition *means*; this trigger is the durable boundary that makes the
 * rule unavoidable for every writer, including one that bypasses the command.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS consents_guard_trigger ON consents');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION consents_guard() RETURNS trigger AS $fn$
            DECLARE
                legal_transition boolean;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'consent evidence is an auditable fact and cannot be deleted'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF TG_OP = 'INSERT' THEN
                    IF NEW.lifecycle_state <> 'draft' THEN
                        RAISE EXCEPTION 'a consent is born draft; verification and activation are separate attributable acts (state: %)', NEW.lifecycle_state
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF char_length(trim(NEW.evidence_ref)) = 0 THEN
                        RAISE EXCEPTION 'a consent requires its evidence reference'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    RETURN NEW;
                END IF;

                -- UPDATE: consent facts are write-once.
                IF NEW.subject_person_id IS DISTINCT FROM OLD.subject_person_id
                    OR NEW.purpose_id IS DISTINCT FROM OLD.purpose_id
                    OR NEW.evidence_ref IS DISTINCT FROM OLD.evidence_ref
                    OR NEW.effective_from IS DISTINCT FROM OLD.effective_from
                    OR NEW.effective_to IS DISTINCT FROM OLD.effective_to
                    OR NEW.recorded_by IS DISTINCT FROM OLD.recorded_by
                    OR NEW.created_at IS DISTINCT FROM OLD.created_at
                THEN
                    RAISE EXCEPTION 'only the lifecycle state may change on a consent; its subject, purpose, evidence and effective window are write-once'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.lifecycle_state = OLD.lifecycle_state THEN
                    RAISE EXCEPTION 'a consent update must move its lifecycle state (state: %)', NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                legal_transition := CASE OLD.lifecycle_state
                    WHEN 'draft' THEN NEW.lifecycle_state = 'submitted'
                    WHEN 'submitted' THEN NEW.lifecycle_state = 'verified'
                    WHEN 'verified' THEN NEW.lifecycle_state = 'active'
                    WHEN 'active' THEN NEW.lifecycle_state IN ('expired', 'revoked', 'archived')
                    WHEN 'expired' THEN NEW.lifecycle_state = 'archived'
                    WHEN 'revoked' THEN NEW.lifecycle_state = 'archived'
                    ELSE false
                END;

                IF NOT legal_transition THEN
                    RAISE EXCEPTION 'a consent moves only forward through draft -> submitted -> verified -> active -> expired|revoked -> archived (state: % -> %)',
                        OLD.lifecycle_state, NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);

        DB::statement('CREATE TRIGGER consents_guard_trigger BEFORE INSERT OR UPDATE OR DELETE ON consents FOR EACH ROW EXECUTE FUNCTION consents_guard()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS consents_guard_trigger ON consents');
        DB::statement('DROP FUNCTION IF EXISTS consents_guard()');
    }
};
