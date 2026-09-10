<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION privacy_evidence_append_only() RETURNS trigger AS $fn$
            BEGIN
                RAISE EXCEPTION 'privacy evidence is append-only';
            END;
            $fn$ LANGUAGE plpgsql
        SQL);

        DB::statement('CREATE TRIGGER disclosures_append_only_trigger BEFORE UPDATE OR DELETE ON disclosures FOR EACH ROW EXECUTE FUNCTION privacy_evidence_append_only()');
        DB::statement('CREATE TRIGGER consent_revocations_append_only_trigger BEFORE UPDATE OR DELETE ON consent_revocations FOR EACH ROW EXECUTE FUNCTION privacy_evidence_append_only()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS disclosures_append_only_trigger ON disclosures');
        DB::statement('DROP TRIGGER IF EXISTS consent_revocations_append_only_trigger ON consent_revocations');
        DB::statement('DROP FUNCTION IF EXISTS privacy_evidence_append_only()');
    }
};
