<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admission decision concurrency boundary.
 *
 * One applicant may have many immutable final decisions over time, but only
 * one proposed/reviewed decision chain may be in flight at a time. The
 * command layer returns a domain rejection; this partial unique index makes
 * the same invariant hold for concurrent writers and alternate transports.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX admission_decisions_one_inflight_per_applicant ON admission_decisions (applicant_id) WHERE lifecycle_state IN ('proposed', 'reviewed')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS admission_decisions_one_inflight_per_applicant');
    }
};
