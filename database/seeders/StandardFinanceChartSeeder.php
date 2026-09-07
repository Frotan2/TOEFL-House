<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Canonical reference-data loader for the standard school chart of accounts.
 *
 * This deliberately lives in seeding rather than schema definition. The
 * historical migration 000186 is retained unchanged for migration-history
 * compatibility, while future canonical-baseline initialization uses this
 * explicit reference-data boundary.
 */
final class StandardFinanceChartSeeder extends Seeder
{
    /** @var list<array{code:string,name:string,type:string}> */
    private const CHART = [
        ['code' => '1000', 'name' => 'Cash & Bank', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Accounts Receivable — Student Tuition', 'type' => 'asset'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
        ['code' => '2010', 'name' => 'Accrued Payroll', 'type' => 'liability'],
        ['code' => '3000', 'name' => 'Opening Fund Balance', 'type' => 'equity'],
        ['code' => '4000', 'name' => 'Tuition & Instruction Revenue', 'type' => 'revenue'],
        ['code' => '4100', 'name' => 'Fees & Other Revenue', 'type' => 'revenue'],
        ['code' => '5000', 'name' => 'Salaries & Benefits Expense', 'type' => 'expense'],
        ['code' => '5100', 'name' => 'Financial Aid & Scholarship Expense', 'type' => 'expense'],
        ['code' => '5200', 'name' => 'Discounts & Allowances', 'type' => 'expense'],
        ['code' => '6000', 'name' => 'Operating & Administrative Expense', 'type' => 'expense'],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::CHART as $account) {
            DB::table('accounts')->insertOrIgnore([
                'id' => RandomIdentifier::new(),
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
