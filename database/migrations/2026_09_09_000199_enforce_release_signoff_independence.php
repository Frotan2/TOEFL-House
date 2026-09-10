<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Terminal academic/placement release is a distinct authority step. The
 * application commands already separate scoring/moderation/approval; this
 * migration makes the final release signer separation non-bypassable for raw
 * SQL and concurrent writers as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION placement_release_signoff_independence_guard() RETURNS trigger AS $fn$
            BEGIN
                IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state = 'released' THEN
                    IF NEW.released_by IS NULL OR trim(NEW.released_by) = '' THEN
                        RAISE EXCEPTION 'placement release requires an attributable releaser'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.released_by IS NOT DISTINCT FROM NEW.approved_by
                       OR NEW.released_by IS NOT DISTINCT FROM NEW.reviewed_by THEN
                        RAISE EXCEPTION 'placement release signer must differ from approver and reviewer'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);

        DB::statement('DROP TRIGGER IF EXISTS placement_release_signoff_independence_guard_trigger ON placement_profiles');
        DB::statement(<<<'SQL'
            CREATE TRIGGER placement_release_signoff_independence_guard_trigger
            BEFORE UPDATE OF lifecycle_state, released_by ON placement_profiles
            FOR EACH ROW
            EXECUTE FUNCTION placement_release_signoff_independence_guard()
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assessment_release_signoff_independence_guard() RETURNS trigger AS $fn$
            BEGIN
                IF OLD.lifecycle_state = 'approved' AND NEW.lifecycle_state = 'released' THEN
                    IF NEW.released_by IS NULL OR trim(NEW.released_by) = '' THEN
                        RAISE EXCEPTION 'assessment release requires an attributable releaser'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.released_by IS NOT DISTINCT FROM NEW.approved_by
                       OR NEW.released_by IS NOT DISTINCT FROM NEW.moderated_by THEN
                        RAISE EXCEPTION 'assessment release signer must differ from approver and moderator'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);

        DB::statement('DROP TRIGGER IF EXISTS assessment_release_signoff_independence_guard_trigger ON assessment_results');
        DB::statement(<<<'SQL'
            CREATE TRIGGER assessment_release_signoff_independence_guard_trigger
            BEFORE UPDATE OF lifecycle_state, released_by ON assessment_results
            FOR EACH ROW
            EXECUTE FUNCTION assessment_release_signoff_independence_guard()
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS placement_release_signoff_independence_guard_trigger ON placement_profiles');
        DB::statement('DROP FUNCTION IF EXISTS placement_release_signoff_independence_guard()');
        DB::statement('DROP TRIGGER IF EXISTS assessment_release_signoff_independence_guard_trigger ON assessment_results');
        DB::statement('DROP FUNCTION IF EXISTS assessment_release_signoff_independence_guard()');
    }
};
