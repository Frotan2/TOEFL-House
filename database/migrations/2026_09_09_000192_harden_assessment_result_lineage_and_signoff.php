<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Final database authority for assessment-result lineage and sign-off.
 *
 * The application command is the human-facing workflow owner, but direct SQL
 * and concurrent writers must observe the same evidence contract:
 *
 *   scored -> moderated -> approved -> released
 *   released -> appealed -> corrected
 *   released -> corrected
 *
 * A correction replacement may be released only after the staged correction
 * itself is approved. Reviewer/approver/releaser provenance is immutable once
 * recorded and a replacement remains linked to the same attempt as its source.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE assessment_results
              ADD CONSTRAINT assessment_results_corrects_fk
              FOREIGN KEY (corrects_id) REFERENCES assessment_results (id)
              NOT VALID
            SQL);
        DB::statement('ALTER TABLE assessment_results VALIDATE CONSTRAINT assessment_results_corrects_fk');
        DB::statement("ALTER TABLE assessment_results ADD CONSTRAINT assessment_results_not_self_corrected CHECK (corrects_id IS NULL OR corrects_id <> id)");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION academic_assessment_result_guard() RETURNS trigger AS $fn$
            DECLARE
                attempt_state text;
                source_state text;
                source_attempt char(36);
                correction_state text;
                correction_score numeric;
                correction_proposer char(36);
                correction_approver char(36);
                allowed boolean := false;
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    SELECT lifecycle_state INTO attempt_state
                      FROM assessment_attempts
                     WHERE id = NEW.attempt_id;
                    IF attempt_state IS DISTINCT FROM 'submitted' THEN
                        RAISE EXCEPTION 'assessment results require a submitted attempt'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    IF NEW.lifecycle_state = 'scored' AND NEW.corrects_id IS NULL THEN
                        IF NEW.moderated_by IS NOT NULL
                           OR NEW.approved_by IS NOT NULL
                           OR NEW.released_by IS NOT NULL
                           OR NEW.correction_reason IS NOT NULL THEN
                            RAISE EXCEPTION 'a base scored result may not carry downstream sign-off or correction provenance'
                                USING ERRCODE = 'check_violation';
                        END IF;
                        RETURN NEW;
                    END IF;

                    IF NEW.lifecycle_state = 'released' AND NEW.corrects_id IS NOT NULL THEN
                        SELECT r.lifecycle_state, r.attempt_id
                          INTO source_state, source_attempt
                          FROM assessment_results r
                         WHERE r.id = NEW.corrects_id;

                        SELECT rc.lifecycle_state, rc.score, rc.proposed_by, rc.approved_by
                          INTO correction_state, correction_score, correction_proposer, correction_approver
                          FROM result_corrections rc
                         WHERE rc.result_id = NEW.corrects_id
                         ORDER BY rc.created_at DESC, rc.id DESC
                         LIMIT 1;

                        IF source_state IS DISTINCT FROM 'corrected'
                           OR source_attempt IS DISTINCT FROM NEW.attempt_id
                           OR correction_state IS DISTINCT FROM 'approved'
                           OR NEW.score IS DISTINCT FROM correction_score
                           OR NEW.scored_by IS DISTINCT FROM correction_proposer
                           OR NEW.correction_reason IS NULL
                           OR char_length(trim(NEW.correction_reason)) = 0
                           OR NEW.moderated_by IS NULL
                           OR NEW.approved_by IS NULL
                           OR NEW.released_by IS NULL
                           OR NEW.approved_by IS DISTINCT FROM correction_approver
                           OR NEW.released_by IS DISTINCT FROM correction_approver
                           OR trim(NEW.approved_by) = trim(NEW.scored_by)
                           OR trim(NEW.approved_by) = trim(NEW.moderated_by)
                        THEN
                            RAISE EXCEPTION 'a replacement result requires an approved, attributable correction workflow'
                                USING ERRCODE = 'check_violation';
                        END IF;
                        RETURN NEW;
                    END IF;

                    RAISE EXCEPTION 'new assessment results must be scored, or released replacements must cite an approved correction'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.attempt_id IS DISTINCT FROM NEW.attempt_id
                   OR OLD.corrects_id IS DISTINCT FROM NEW.corrects_id
                   OR OLD.score IS DISTINCT FROM NEW.score
                   OR OLD.correction_reason IS DISTINCT FROM NEW.correction_reason
                   OR OLD.scored_by IS DISTINCT FROM NEW.scored_by
                   OR OLD.created_at IS DISTINCT FROM NEW.created_at
                THEN
                    RAISE EXCEPTION 'assessment result evidence identity is immutable'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF OLD.lifecycle_state = 'scored' AND NEW.lifecycle_state = 'moderated' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'moderated' AND NEW.lifecycle_state = 'approved' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state = 'released' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'released' AND NEW.lifecycle_state IN ('appealed', 'corrected') THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'appealed' AND NEW.lifecycle_state = 'corrected' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = NEW.lifecycle_state THEN allowed := true; END IF;
                IF NOT allowed THEN
                    RAISE EXCEPTION 'assessment result lifecycle transition is not allowed: % -> %', OLD.lifecycle_state, NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.lifecycle_state = 'scored' THEN
                    IF NEW.moderated_by IS NOT NULL OR NEW.approved_by IS NOT NULL OR NEW.released_by IS NOT NULL THEN
                        RAISE EXCEPTION 'a scored result has no downstream sign-off actors'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF NEW.lifecycle_state = 'moderated' THEN
                    IF NEW.moderated_by IS NULL OR trim(NEW.moderated_by) = trim(NEW.scored_by)
                       OR NEW.approved_by IS NOT NULL OR NEW.released_by IS NOT NULL THEN
                        RAISE EXCEPTION 'moderation requires a distinct moderator and no later sign-offs'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF OLD.lifecycle_state = 'moderated'
                       AND NEW.moderated_by IS DISTINCT FROM OLD.moderated_by THEN
                        RAISE EXCEPTION 'the recorded moderator is immutable'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF NEW.lifecycle_state = 'approved' THEN
                    IF NEW.moderated_by IS NULL
                       OR NEW.approved_by IS NULL
                       OR trim(NEW.moderated_by) = trim(NEW.scored_by)
                       OR trim(NEW.approved_by) = trim(NEW.scored_by)
                       OR trim(NEW.approved_by) = trim(NEW.moderated_by)
                       OR NEW.released_by IS NOT NULL
                    THEN
                        RAISE EXCEPTION 'approval requires a preserved moderator, a distinct approver, and no releaser yet'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF OLD.lifecycle_state = 'approved'
                       OR (OLD.lifecycle_state = 'moderated' AND NEW.moderated_by IS DISTINCT FROM OLD.moderated_by) THEN
                        RAISE EXCEPTION 'the recorded moderator may not change at approval'
                            USING ERRCODE = 'check_violation';
                    END IF;
                ELSIF NEW.lifecycle_state = 'released' THEN
                    IF NEW.moderated_by IS NULL
                       OR NEW.approved_by IS NULL
                       OR NEW.released_by IS NULL
                       OR trim(NEW.moderated_by) = trim(NEW.scored_by)
                       OR trim(NEW.approved_by) = trim(NEW.scored_by)
                       OR trim(NEW.approved_by) = trim(NEW.moderated_by)
                    THEN
                        RAISE EXCEPTION 'release requires complete preserved sign-off provenance'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF OLD.lifecycle_state = 'released' THEN
                        IF NEW.moderated_by IS DISTINCT FROM OLD.moderated_by
                           OR NEW.approved_by IS DISTINCT FROM OLD.approved_by
                           OR NEW.released_by IS DISTINCT FROM OLD.released_by THEN
                            RAISE EXCEPTION 'released result sign-off identities are immutable'
                                USING ERRCODE = 'check_violation';
                        END IF;
                    ELSIF OLD.lifecycle_state = 'approved' THEN
                        IF NEW.moderated_by IS DISTINCT FROM OLD.moderated_by
                           OR NEW.approved_by IS DISTINCT FROM OLD.approved_by THEN
                            RAISE EXCEPTION 'moderator and approver provenance must survive release unchanged'
                                USING ERRCODE = 'check_violation';
                        END IF;
                    END IF;
                ELSIF NEW.lifecycle_state IN ('appealed', 'corrected') THEN
                    IF NEW.moderated_by IS NULL OR NEW.approved_by IS NULL OR NEW.released_by IS NULL THEN
                        RAISE EXCEPTION 'appealed or corrected results must preserve complete prior sign-off provenance'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.moderated_by IS DISTINCT FROM OLD.moderated_by
                       OR NEW.approved_by IS DISTINCT FROM OLD.approved_by
                       OR NEW.released_by IS DISTINCT FROM OLD.released_by THEN
                        RAISE EXCEPTION 'appealed/corrected result sign-off provenance is immutable'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                IF OLD.lifecycle_state = NEW.lifecycle_state
                   AND (NEW.moderated_by IS DISTINCT FROM OLD.moderated_by
                        OR NEW.approved_by IS DISTINCT FROM OLD.approved_by
                        OR NEW.released_by IS DISTINCT FROM OLD.released_by)
                THEN
                    RAISE EXCEPTION 'assessment result sign-off identities cannot be rewritten in place'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.lifecycle_state = 'corrected'
                   AND NOT EXISTS (
                       SELECT 1
                         FROM result_corrections rc
                        WHERE rc.result_id = NEW.id
                          AND rc.lifecycle_state = 'proposed'
                   )
                THEN
                    RAISE EXCEPTION 'correcting an assessment result requires its staged correction proposal before replacement approval commits'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assessment_results DROP CONSTRAINT IF EXISTS assessment_results_not_self_corrected");
        DB::statement('ALTER TABLE assessment_results DROP CONSTRAINT IF EXISTS assessment_results_corrects_fk');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION academic_assessment_result_guard() RETURNS trigger AS $fn$
            DECLARE
                attempt_state text;
                source_state text;
                source_attempt char(36);
                source_correction boolean;
                correction_score numeric;
                correction_proposer char(36);
                allowed boolean := false;
            BEGIN
                IF TG_OP = 'INSERT' THEN
                    SELECT lifecycle_state INTO attempt_state FROM assessment_attempts WHERE id = NEW.attempt_id;
                    IF attempt_state IS DISTINCT FROM 'submitted' THEN
                        RAISE EXCEPTION 'assessment results require a submitted attempt'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.lifecycle_state = 'scored' AND NEW.corrects_id IS NULL THEN
                        RETURN NEW;
                    END IF;
                    IF NEW.lifecycle_state = 'released' AND NEW.corrects_id IS NOT NULL THEN
                        SELECT lifecycle_state, attempt_id INTO source_state, source_attempt
                          FROM assessment_results WHERE id = NEW.corrects_id;
                        SELECT rc.score, rc.proposed_by, (rc.lifecycle_state = 'proposed')
                          INTO correction_score, correction_proposer, source_correction
                          FROM result_corrections rc
                         WHERE rc.result_id = NEW.corrects_id
                         ORDER BY rc.created_at DESC
                         LIMIT 1;
                        IF source_state IS DISTINCT FROM 'corrected'
                           OR source_attempt IS DISTINCT FROM NEW.attempt_id
                           OR NOT source_correction
                           OR NEW.score IS DISTINCT FROM correction_score
                           OR NEW.scored_by IS DISTINCT FROM correction_proposer
                           OR NEW.correction_reason IS NULL
                           OR char_length(trim(NEW.correction_reason)) = 0 THEN
                            RAISE EXCEPTION 'a replacement result requires an approved correction workflow'
                                USING ERRCODE = 'check_violation';
                        END IF;
                        RETURN NEW;
                    END IF;
                    RAISE EXCEPTION 'new assessment results must be scored, or released replacements must cite a correction'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.attempt_id IS DISTINCT FROM NEW.attempt_id
                   OR OLD.corrects_id IS DISTINCT FROM NEW.corrects_id
                   OR OLD.score IS DISTINCT FROM NEW.score
                   OR OLD.correction_reason IS DISTINCT FROM NEW.correction_reason
                   OR OLD.scored_by IS DISTINCT FROM NEW.scored_by
                   OR OLD.created_at IS DISTINCT FROM NEW.created_at THEN
                    RAISE EXCEPTION 'assessment result evidence identity is immutable'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF OLD.lifecycle_state = 'scored' AND NEW.lifecycle_state = 'moderated' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'moderated' AND NEW.lifecycle_state = 'approved' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state = 'released' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'released' AND NEW.lifecycle_state IN ('appealed', 'corrected') THEN allowed := true; END IF;
                IF OLD.lifecycle_state = 'appealed' AND NEW.lifecycle_state = 'corrected' THEN allowed := true; END IF;
                IF OLD.lifecycle_state = NEW.lifecycle_state THEN allowed := true; END IF;
                IF NOT allowed THEN
                    RAISE EXCEPTION 'assessment result lifecycle transition is not allowed: % -> %', OLD.lifecycle_state, NEW.lifecycle_state
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'moderated' AND (NEW.moderated_by IS NULL OR trim(NEW.moderated_by) = trim(NEW.scored_by)) THEN
                    RAISE EXCEPTION 'moderating an assessment result requires a distinct moderator'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'approved' AND (NEW.approved_by IS NULL OR trim(NEW.approved_by) = trim(NEW.scored_by) OR trim(NEW.approved_by) = trim(NEW.moderated_by)) THEN
                    RAISE EXCEPTION 'approving an assessment result requires a distinct approver'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'released' AND NEW.released_by IS NULL THEN
                    RAISE EXCEPTION 'releasing an assessment result requires a releaser'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.lifecycle_state = 'corrected' AND NOT EXISTS (
                    SELECT 1 FROM result_corrections rc
                     WHERE rc.result_id = NEW.id
                       AND rc.lifecycle_state = 'proposed'
                ) THEN
                    RAISE EXCEPTION 'correcting an assessment result requires a proposed correction'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);
    }
};
