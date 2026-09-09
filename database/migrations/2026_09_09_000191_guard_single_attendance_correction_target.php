<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An attendance correction advances one immutable fact to exactly one
 * successor. Without a database uniqueness boundary, two direct corrections
 * could target the same original fact and leave transcript reconstruction
 * with multiple competing current facts.
 *
 * The Academic command rejects the same condition with a domain error; this
 * partial unique index closes the SQL boundary as well and also serializes
 * concurrent correction attempts safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX attendance_facts_single_correction_target_idx
                ON attendance_facts (corrects_id)
                WHERE corrects_id IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_facts_single_correction_target_idx');
    }
};
