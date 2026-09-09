<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return;
        }

        DB::statement("CREATE OR REPLACE FUNCTION prevent_payroll_adjustment_monetary_writes() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'payroll_adjustments is retired: Finance is the sole monetary authority for payroll corrections' USING ERRCODE = 'P0001'; END; $$");
        DB::statement('DROP TRIGGER IF EXISTS payroll_adjustment_finance_authority_guard ON payroll_adjustments');
        DB::statement('CREATE TRIGGER payroll_adjustment_finance_authority_guard BEFORE INSERT OR UPDATE ON payroll_adjustments FOR EACH ROW EXECUTE FUNCTION prevent_payroll_adjustment_monetary_writes()');
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS payroll_adjustment_finance_authority_guard ON payroll_adjustments');
        DB::statement('DROP FUNCTION IF EXISTS prevent_payroll_adjustment_monetary_writes()');
    }
};
