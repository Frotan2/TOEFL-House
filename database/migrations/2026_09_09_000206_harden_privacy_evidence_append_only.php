<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Both tables already have table-local append-only triggers from the
        // original schema. PostgreSQL does not replace triggers by name, so
        // explicitly replace those trigger bindings rather than attempting to
        // stack a second trigger with the same table-local name.
        DB::statement('DROP TRIGGER IF EXISTS disclosures_append_only_trigger ON disclosures');
        DB::statement('DROP TRIGGER IF EXISTS consent_revocations_append_only_trigger ON consent_revocations');

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

        // Restore the exact pre-000206 guards. A rollback must not leave
        // disclosure or consent-revocation evidence writable.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION disclosures_append_only() RETURNS trigger AS $fn$
            BEGIN
                RAISE EXCEPTION 'disclosures is append-only';
            END;
            $fn$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER disclosures_append_only_trigger BEFORE UPDATE OR DELETE ON disclosures FOR EACH ROW EXECUTE FUNCTION disclosures_append_only()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION consent_revocations_append_only() RETURNS trigger AS $fn$
            BEGIN
                RAISE EXCEPTION 'consent_revocations is append-only';
            END;
            $fn$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER consent_revocations_append_only_trigger BEFORE UPDATE OR DELETE ON consent_revocations FOR EACH ROW EXECUTE FUNCTION consent_revocations_append_only()');
    }
};
