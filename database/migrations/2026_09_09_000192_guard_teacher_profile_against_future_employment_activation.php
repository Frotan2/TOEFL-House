<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A teacher capability profile is only legal when the linked employment is
 * effective-active today. HR may schedule future status facts, but those facts
 * must not be treated as current teacher eligibility by direct writers.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION teacher_profile_effective_employment_guard() RETURNS trigger AS $fn$
            DECLARE
                effective_status text;
            BEGIN
                SELECT es.status
                  INTO effective_status
                  FROM employment_statuses es
                 WHERE es.employment_id = NEW.employment_id
                   AND es.effective_from <= CURRENT_DATE
                 ORDER BY es.effective_from DESC, es.seq DESC
                 LIMIT 1;

                IF effective_status IS DISTINCT FROM 'active' THEN
                    RAISE EXCEPTION 'teacher profile requires employment that is active as of the registration date'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $fn$ LANGUAGE plpgsql;
            SQL);

        DB::statement('DROP TRIGGER IF EXISTS teacher_profile_effective_employment_guard_trigger ON teacher_profiles');
        DB::statement(<<<'SQL'
            CREATE TRIGGER teacher_profile_effective_employment_guard_trigger
            BEFORE INSERT OR UPDATE OF employment_id ON teacher_profiles
            FOR EACH ROW
            EXECUTE FUNCTION teacher_profile_effective_employment_guard()
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS teacher_profile_effective_employment_guard_trigger ON teacher_profiles');
        DB::statement('DROP FUNCTION IF EXISTS teacher_profile_effective_employment_guard()');
    }
};