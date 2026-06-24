<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test data for the Accounts module (categories + income/expense transactions).
 *
 * Run with:  php artisan db:seed --class=AccountModuleTestSeeder
 *
 * Idempotent. Per branch: a few Income/Expense categories and transactions with mixed
 * statuses (Approved/Pending/Rejected) so the dashboard, income, expense and category
 * tabs all have data. branch_id / school_id / academic_year_id populated.
 */
class AccountModuleTestSeeder extends Seeder
{
    public function run(): void
    {
        $ayId = DB::table('academic_years')->where('is_current', 1)->value('id')
            ?? DB::table('academic_years')->orderBy('id')->value('id');

        $month = date('F');
        $fy = $this->financialYear();
        $today = date('Y-m-d');

        $cats = [
            ['name' => 'Tuition Income',   'code' => 'INC-TUI', 'type' => 'Income',  'sub' => 'Operating'],
            ['name' => 'Donation',         'code' => 'INC-DON', 'type' => 'Income',  'sub' => 'Other'],
            ['name' => 'Staff Salaries',   'code' => 'EXP-SAL', 'type' => 'Expense', 'sub' => 'Payroll'],
            ['name' => 'Utilities',        'code' => 'EXP-UTL', 'type' => 'Expense', 'sub' => 'Operating'],
            ['name' => 'Maintenance',      'code' => 'EXP-MNT', 'type' => 'Expense', 'sub' => 'Operating'],
        ];

        $branchIds = DB::table('branches')->whereNull('deleted_at')->where('is_active', 1)->orderBy('id')->limit(3)->pluck('id');

        $totalCats = 0; $totalTx = 0;
        foreach ($branchIds as $branchId) {
            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id');
            $creator = DB::table('users')->where('branch_id', $branchId)->where('role', 'BranchAdmin')->value('id')
                ?? DB::table('users')->where('branch_id', $branchId)->value('id');

            $catIds = [];
            foreach ($cats as $c) {
                $cat = AccountCategory::firstOrCreate(
                    ['school_id' => $schoolId, 'code' => $c['code']],
                    [
                        'branch_id' => $branchId,
                        'academic_year_id' => $ayId,
                        'name' => $c['name'],
                        'type' => $c['type'],
                        'sub_type' => $c['sub'],
                        'description' => $c['name'] . ' (test data)',
                        'is_active' => true,
                    ]
                );
                $catIds[$c['code']] = ['id' => $cat->id, 'type' => $c['type']];
                $totalCats++;
            }

            // Transactions: a spread of income + expense, mixed statuses
            $plan = [
                ['code' => 'INC-TUI', 'amount' => 250000, 'status' => 'Approved', 'method' => 'Bank Transfer', 'party' => 'Fee Collection', 'day' => 2],
                ['code' => 'INC-DON', 'amount' => 50000,  'status' => 'Approved', 'method' => 'UPI',           'party' => 'Alumni Trust',  'day' => 5],
                ['code' => 'INC-TUI', 'amount' => 120000, 'status' => 'Pending',  'method' => 'Cash',          'party' => 'Fee Collection', 'day' => 8],
                ['code' => 'EXP-SAL', 'amount' => 180000, 'status' => 'Approved', 'method' => 'Bank Transfer', 'party' => 'Payroll',       'day' => 1],
                ['code' => 'EXP-UTL', 'amount' => 24000,  'status' => 'Approved', 'method' => 'Check',        'party' => 'Power Board',   'day' => 6],
                ['code' => 'EXP-MNT', 'amount' => 15000,  'status' => 'Pending',  'method' => 'Cash',          'party' => 'ABC Repairs',   'day' => 9],
                ['code' => 'EXP-UTL', 'amount' => 8000,   'status' => 'Rejected', 'method' => 'Cash',          'party' => 'Water Supply',  'day' => 10],
            ];

            foreach ($plan as $i => $p) {
                $meta = $catIds[$p['code']];
                $txNumber = ($meta['type'] === 'Income' ? 'INC' : 'EXP') . '-B' . $branchId . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
                Transaction::firstOrCreate(
                    ['transaction_number' => $txNumber],
                    [
                        'branch_id' => $branchId,
                        'school_id' => $schoolId,
                        'category_id' => $meta['id'],
                        'transaction_date' => substr($fy, 0, 4) . '-' . date('m') . '-' . str_pad((string) $p['day'], 2, '0', STR_PAD_LEFT),
                        'type' => $meta['type'],
                        'amount' => $p['amount'],
                        'party_name' => $p['party'],
                        'party_type' => $meta['type'] === 'Income' ? 'Customer' : 'Vendor',
                        'payment_method' => $p['method'],
                        'description' => $p['party'] . ' - ' . $p['code'],
                        'status' => $p['status'],
                        'created_by' => $creator,
                        'approved_by' => $p['status'] === 'Approved' ? $creator : null,
                        'approved_at' => $p['status'] === 'Approved' ? now() : null,
                        'financial_year' => $fy,
                        'month' => $month,
                    ]
                );
                $totalTx++;
            }

            $this->command?->info("Branch {$branchId}: " . count($cats) . " categories + " . count($plan) . " transactions seeded.");
        }

        $this->command?->info("Done. ~{$totalCats} categories, ~{$totalTx} transactions across " . $branchIds->count() . ' branch(es). FY {$fy}.');
    }

    private function financialYear(): string
    {
        $m = (int) date('n');
        $y = (int) date('Y');
        return $m < 4 ? ($y - 1) . '-' . $y : $y . '-' . ($y + 1);
    }
}
