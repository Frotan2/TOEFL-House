<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The command layer records at most one verdict per version: verify
        // requires the submitted state and the verdict moves the state, so a
        // second verdict for the same (document, version) is unreachable
        // through authority. This index makes that evidence invariant hold
        // against out-of-band writes too — contradictory verdicts about one
        // version can never coexist, and the append-only trigger keeps the
        // recorded verdict unrevisable.
        DB::statement('CREATE UNIQUE INDEX document_verifications_one_verdict_per_version ON document_verifications (document_id, version_no)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_verifications_one_verdict_per_version');
    }
};
