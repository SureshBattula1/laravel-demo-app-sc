<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountsModuleSeeder extends Seeder
{
    /**
     * Run the database seeds - Create sample transactions for Accounts module
     */
    public function run(): void
    {
        $this->command->info('Seeding Accounts Module with sample transactions...');

        // Get the first branch and user
        $branch = DB::table('branches')->first();
        $user = DB::table('users')->where('role', 'SuperAdmin')->orWhere('role', 'BranchAdmin')->first();

        if (!$branch || !$user) {
            $this->command->warn('No branch or user found. Please seed branches and users first.');
            return;
        }

        $currentYear = date('Y');
        $financialYear = $currentYear . '-' . ($currentYear + 1);

        // Get category IDs
        $studentFeeCat = DB::table('account_categories')->where('code', 'INC-FEE')->first();
        $admissionFeeCat = DB::table('account_categories')->where('code', 'INC-ADM')->first();
        $donationCat = DB::table('account_categories')->where('code', 'INC-DON')->first();
        
        $teacherSalaryCat = DB::table('account_categories')->where('code', 'EXP-SAL-TCH')->first();
        $maintenanceCat = DB::table('account_categories')->where('code', 'EXP-MNT-BLD')->first();
        $electricityCat = DB::table('account_categories')->where('code', 'EXP-UTL-ELC')->first();
        $stationeryCat = DB::table('account_categories')->where('code', 'EXP-SUP-STA')->first();
        $tipsCat = DB::table('account_categories')->where('code', 'EXP-TIP')->first();

        $transactions = [];

        // INCOME TRANSACTIONS
        // 1. Student Fees - Multiple transactions
        for ($i = 1; $i <= 5; $i++) {
            $transactions[] = [
                'branch_id' => $branch->id,
                'category_id' => $studentFeeCat->id,
                'transaction_number' => 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5($i), 0, 4)),
                'transaction_date' => Carbon::now()->subDays(rand(1, 30)),
                'type' => 'Income',
                'amount' => rand(40000, 60000),
                'party_name' => 'Student Batch ' . $i,
                'party_type' => 'Student',
                'payment_method' => ['Cash', 'Bank Transfer', 'UPI', 'Check'][rand(0, 3)],
                'payment_reference' => null,
                'bank_name' => null,
                'description' => 'Monthly tuition fees - ' . Carbon::now()->format('F Y'),
                'notes' => 'Regular monthly fee collection',
                'status' => 'Approved',
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
                'financial_year' => $financialYear,
                'month' => Carbon::now()->format('F'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        // 2. Admission Fees
        $transactions[] = [
            'branch_id' => $branch->id,
            'category_id' => $admissionFeeCat->id,
            'transaction_number' => 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5('adm'), 0, 4)),
            'transaction_date' => Carbon::now()->subDays(15),
            'type' => 'Income',
            'amount' => 75000,
            'party_name' => 'New Admissions',
            'party_type' => 'Student',
            'payment_method' => 'Bank Transfer',
            'payment_reference' => null,
            'bank_name' => null,
            'description' => 'Admission fees for new students',
            'notes' => '15 new students admitted',
            'status' => 'Approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'financial_year' => $financialYear,
            'month' => Carbon::now()->format('F'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        // 3. Donations
        $transactions[] = [
            'branch_id' => $branch->id,
            'category_id' => $donationCat->id,
            'transaction_number' => 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5('don'), 0, 4)),
            'transaction_date' => Carbon::now()->subDays(10),
            'type' => 'Income',
            'amount' => 25000,
            'party_name' => 'Alumni Association',
            'party_type' => 'Organization',
            'payment_method' => 'Check',
            'payment_reference' => 'CHK-2024-1001',
            'bank_name' => null,
            'description' => 'Annual alumni donation',
            'notes' => 'For library development',
            'status' => 'Approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'financial_year' => $financialYear,
            'month' => Carbon::now()->format('F'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        // EXPENSE TRANSACTIONS
        // 1. Teacher Salaries
        for ($i = 1; $i <= 3; $i++) {
            $transactions[] = [
                'branch_id' => $branch->id,
                'category_id' => $teacherSalaryCat->id,
                'transaction_number' => 'EXP-' . date('Ymd') . '-' . strtoupper(substr(md5('sal' . $i), 0, 4)),
                'transaction_date' => Carbon::now()->subDays(5),
                'type' => 'Expense',
                'amount' => rand(35000, 50000),
                'party_name' => 'Teaching Staff Batch ' . $i,
                'party_type' => 'Teacher',
                'payment_method' => 'Bank Transfer',
                'payment_reference' => null,
                'bank_name' => null,
                'description' => 'Teacher salaries - ' . Carbon::now()->format('F Y'),
                'notes' => 'Monthly salary payment',
                'status' => 'Approved',
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => Carbon::now(),
                'financial_year' => $financialYear,
                'month' => Carbon::now()->format('F'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        // 2. Maintenance
        $transactions[] = [
            'branch_id' => $branch->id,
            'category_id' => $maintenanceCat->id,
            'transaction_number' => 'EXP-' . date('Ymd') . '-' . strtoupper(substr(md5('mnt'), 0, 4)),
            'transaction_date' => Carbon::now()->subDays(12),
            'type' => 'Expense',
            'amount' => 15000,
            'party_name' => 'BuildCare Services',
            'party_type' => 'Vendor',
            'payment_method' => 'Cash',
            'payment_reference' => null,
            'bank_name' => null,
            'description' => 'Building maintenance and repairs',
            'notes' => 'Roof repair and painting',
            'status' => 'Approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'financial_year' => $financialYear,
            'month' => Carbon::now()->format('F'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        // 3. Electricity
        $transactions[] = [
            'branch_id' => $branch->id,
            'category_id' => $electricityCat->id,
            'transaction_number' => 'EXP-' . date('Ymd') . '-' . strtoupper(substr(md5('elc'), 0, 4)),
            'transaction_date' => Carbon::now()->subDays(8),
            'type' => 'Expense',
            'amount' => 8500,
            'party_name' => 'State Electricity Board',
            'party_type' => 'Utility',
            'payment_method' => 'Bank Transfer',
            'payment_reference' => 'BILL-OCT-2025',
            'bank_name' => null,
            'description' => 'Electricity bill - October 2025',
            'notes' => null,
            'status' => 'Approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'financial_year' => $financialYear,
            'month' => Carbon::now()->format('F'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        // 4. Stationery
        $transactions[] = [
            'branch_id' => $branch->id,
            'category_id' => $stationeryCat->id,
            'transaction_number' => 'EXP-' . date('Ymd') . '-' . strtoupper(substr(md5('sta'), 0, 4)),
            'transaction_date' => Carbon::now()->subDays(20),
            'type' => 'Expense',
            'amount' => 12000,
            'party_name' => 'Office Supplies Ltd',
            'party_type' => 'Vendor',
            'payment_method' => 'Check',
            'payment_reference' => 'CHK-5678',
            'bank_name' => null,
            'description' => 'Stationery and office supplies',
            'notes' => 'Bulk purchase for semester',
            'status' => 'Approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'financial_year' => $financialYear,
            'month' => Carbon::now()->format('F'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        // 5. Tips & Gratuity
        $transactions[] = [
            'branch_id' => $branch->id,
            'category_id' => $tipsCat->id,
            'transaction_number' => 'EXP-' . date('Ymd') . '-' . strtoupper(substr(md5('tip'), 0, 4)),
            'transaction_date' => Carbon::now()->subDays(3),
            'type' => 'Expense',
            'amount' => 2500,
            'party_name' => 'Support Staff',
            'party_type' => 'Staff',
            'payment_method' => 'Cash',
            'payment_reference' => null,
            'bank_name' => null,
            'description' => 'Tips and gratuity for support staff',
            'notes' => 'Monthly appreciation',
            'status' => 'Approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
            'financial_year' => $financialYear,
            'month' => Carbon::now()->format('F'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];

        // Insert all transactions
        DB::table('transactions')->insert($transactions);

        $this->command->info('✓ Created ' . count($transactions) . ' sample transactions');
        
        // Calculate totals
        $totalIncome = collect($transactions)->where('type', 'Income')->sum('amount');
        $totalExpense = collect($transactions)->where('type', 'Expense')->sum('amount');
        
        $this->command->info('  Total Income: ₹' . number_format($totalIncome, 2));
        $this->command->info('  Total Expense: ₹' . number_format($totalExpense, 2));
        $this->command->info('  Net Balance: ₹' . number_format($totalIncome - $totalExpense, 2));
        $this->command->info('✓ Accounts module seeding complete!');
    }
}

