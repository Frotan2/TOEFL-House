<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Payroll\Commands\ApprovePayrollResult;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FinancePayrollAuthorityFeatureTest extends TestCase
{
    public function test_payroll_approval_has_no_monetary_adjustment_writer(): void
    {
        $reflection = new \ReflectionClass(ApprovePayrollResult::class);

        self::assertFalse($reflection->hasMethod('adjust'));
        self::assertFalse(class_exists('App\\Modules\\Payroll\\Models\\PayrollAdjustment'));
    }

    public function test_legacy_payroll_adjustment_table_is_database_write_protected(): void
    {
        $trigger = DB::selectOne(<<<'SQL'
            SELECT COUNT(*)::int AS count
            FROM pg_trigger
            WHERE tgname = 'payroll_adjustment_finance_authority_guard'
              AND NOT tgisinternal
        SQL);

        self::assertSame(1, (int) ($trigger->count ?? 0));
    }
}
