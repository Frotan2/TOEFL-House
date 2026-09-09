<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An attendance session/seat has one base fact and each immutable fact has at
 * most one direct correction. Without database uniqueness boundaries, duplicate
 * marks or two corrections of one fact can make transcript reconstruction
 * order-dependent.
 *
 * The Academic command rejects the same conditions with domain errors; these
 * partial unique indexes close direct-SQL and concurrent write bypasses.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX attendance_facts_single_base_mark_idx
                ON attendance_facts (session_id, enrollment_id)
                WHERE corrects_id IS NULL
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX attendance_facts_single_correction_target_idx
                ON attendance_facts (corrects_id)
                WHERE corrects_id IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_facts_single_correction_target_idx');
        DB::statement('DROP INDEX IF EXISTS attendance_facts_single_base_mark_idx');
    }
};
