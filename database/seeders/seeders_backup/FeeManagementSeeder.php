<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FeeManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding Fee Management Data...');

        // Get some branches and users
        $branches = DB::table('branches')->limit(3)->get();
        if ($branches->isEmpty()) {
            $this->command->error('No branches found. Please run BranchSeeder first.');
            return;
        }

        // Try to get admin, teacher, or any user
        $user = DB::table('users')->whereIn('role', ['Admin', 'Teacher', 'Staff'])->first();
        if (!$user) {
            $user = DB::table('users')->first();
        }
        
        if (!$user) {
            $this->command->error('No users found in database.');
            return;
        }
        
        $adminId = $user->id;
        $academicYear = '2024-2025';
        
        // Clear existing data (disable foreign key checks)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('fee_payments')->truncate();
        DB::table('fee_structures')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->command->info('Creating Fee Structures...');
        
        $feeStructures = [];
        $feeTypes = ['Tuition', 'Library', 'Laboratory', 'Sports', 'Transport', 'Exam'];
        $grades = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
        
        foreach ($branches as $branch) {
            foreach ($grades as $grade) {
                foreach ($feeTypes as $feeType) {
                    // Different amounts for different fee types
                    $amount = match($feeType) {
                        'Tuition' => rand(8000, 15000),
                        'Library' => rand(500, 1500),
                        'Laboratory' => rand(1000, 3000),
                        'Sports' => rand(500, 2000),
                        'Transport' => rand(2000, 5000),
                        'Exam' => rand(500, 1500),
                        default => 1000
                    };
                    
                    $feeStructures[] = [
                        'id' => (string) Str::uuid(),
                        'branch_id' => $branch->id,
                        'grade' => $grade,
                        'fee_type' => $feeType,
                        'amount' => $amount,
                        'academic_year' => $academicYear,
                        'due_date' => Carbon::now()->addMonths(rand(1, 3))->format('Y-m-d'),
                        'description' => "{$feeType} fee for Grade {$grade} - {$academicYear}",
                        'is_recurring' => $feeType === 'Tuition' || $feeType === 'Transport',
                        'recurrence_period' => ($feeType === 'Tuition' || $feeType === 'Transport') ? 'Monthly' : null,
                        'is_active' => true,
                        'created_by' => $adminId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }
        
        // Insert in chunks to avoid memory issues
        $chunks = array_chunk($feeStructures, 100);
        foreach ($chunks as $chunk) {
            DB::table('fee_structures')->insert($chunk);
        }
        
        $this->command->info('Created ' . count($feeStructures) . ' fee structures.');
        
        // Get some students
        $students = DB::table('users')->where('role', 'Student')->limit(20)->get();
        
        if ($students->isEmpty()) {
            $this->command->warn('No students found. Skipping fee payments.');
            return;
        }
        
        $this->command->info('Creating Fee Payments...');
        
        $feePayments = [];
        $paymentMethods = ['Cash', 'Card', 'Online', 'Cheque'];
        $paymentStatuses = ['Completed', 'Completed', 'Completed', 'Pending']; // More completed than pending
        
        // Get created fee structures
        $createdStructures = DB::table('fee_structures')->limit(50)->get();
        
        foreach ($students as $student) {
            // Each student pays 2-5 random fees
            $numberOfPayments = rand(2, 5);
            $selectedStructures = $createdStructures->random(min($numberOfPayments, $createdStructures->count()));
            
            foreach ($selectedStructures as $structure) {
                $amountPaid = $structure->amount;
                $discountAmount = rand(0, 1) ? 0 : rand(100, 500); // 50% chance of discount
                $lateFee = rand(0, 1) ? 0 : rand(50, 200); // 50% chance of late fee
                $totalAmount = $amountPaid + $lateFee - $discountAmount;
                
                $feePayments[] = [
                    'id' => (string) Str::uuid(),
                    'fee_structure_id' => $structure->id,
                    'student_id' => $student->id,
                    'amount_paid' => $amountPaid,
                    'payment_date' => Carbon::now()->subDays(rand(1, 90))->format('Y-m-d H:i:s'),
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'transaction_id' => 'TXN' . strtoupper(Str::random(10)),
                    'receipt_number' => 'RCPT-' . strtoupper(substr(Str::uuid(), 0, 8)),
                    'discount_amount' => $discountAmount,
                    'late_fee' => $lateFee,
                    'total_amount' => $totalAmount,
                    'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                    'remarks' => rand(0, 1) ? 'Payment via school office' : null,
                    'created_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        // Insert payments
        if (!empty($feePayments)) {
            $chunks = array_chunk($feePayments, 100);
            foreach ($chunks as $chunk) {
                DB::table('fee_payments')->insert($chunk);
            }
            $this->command->info('Created ' . count($feePayments) . ' fee payments.');
        }
        
        $this->command->info('✅ Fee Management data seeded successfully!');
        $this->command->info('Summary:');
        $this->command->info('- Fee Structures: ' . count($feeStructures));
        $this->command->info('- Fee Payments: ' . count($feePayments));
    }
}

