<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test data for the Fee Management module.
 *
 * Run with:  php artisan db:seed --class=FeeModuleTestSeeder
 *
 * Idempotent. Per branch: fee types, fee structures (per grade), and fee payments —
 * including some dated TODAY so the "Today's Payments" tab shows data. Mixed
 * Completed/Partial statuses and methods. branch_id/school_id/academic_year_id populated.
 */
class FeeModuleTestSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::current()->first() ?? AcademicYear::orderBy('id')->first();
        $ayId = $year?->id;
        $ayName = $year?->name ?? (date('Y') . '-' . (date('Y') + 1));
        $today = date('Y-m-d');

        // fee type catalog (code suffixed per-branch to respect the global-unique code constraint)
        $types = [
            ['name' => 'Tuition Fee',   'code' => 'TUI',  'mandatory' => true],
            ['name' => 'Transport Fee', 'code' => 'TRA',  'mandatory' => false],
            ['name' => 'Exam Fee',      'code' => 'EXM',  'mandatory' => true],
            ['name' => 'Library Fee',   'code' => 'LIB',  'mandatory' => false],
        ];

        $branchIds = DB::table('branches')->whereNull('deleted_at')->where('is_active', 1)->orderBy('id')->limit(3)->pluck('id');

        $tStruct = 0; $tPay = 0;
        foreach ($branchIds as $branchId) {
            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id');
            $creator = DB::table('users')->where('branch_id', $branchId)->where('role', 'BranchAdmin')->value('id')
                ?? DB::table('users')->where('branch_id', $branchId)->value('id');

            // 1) Fee types
            foreach ($types as $t) {
                FeeType::firstOrCreate(
                    ['branch_id' => $branchId, 'code' => $t['code'] . '-B' . $branchId],
                    [
                        'name' => $t['name'],
                        'description' => $t['name'] . ' (test data)',
                        'academic_year_id' => $ayId,
                        'school_id' => $schoolId,
                        'is_mandatory' => $t['mandatory'],
                        'is_refundable' => false,
                        'is_active' => true,
                    ]
                );
            }

            // 2) Fee structures — one Tuition structure per grade that has students
            $grades = DB::table('students')->where('branch_id', $branchId)->whereNull('deleted_at')
                ->distinct()->orderBy('grade')->limit(3)->pluck('grade');

            foreach ($grades as $grade) {
                $amount = 40000 + ((int) $grade * 1000);
                $structure = FeeStructure::firstOrCreate(
                    ['branch_id' => $branchId, 'grade' => (string) $grade, 'fee_type' => 'Tuition Fee', 'academic_year' => $ayName],
                    [
                        'school_id' => $schoolId,
                        'amount' => $amount,
                        'academic_year_id' => $ayId,
                        'due_date' => substr($ayName, 0, 4) . '-07-15',
                        'description' => 'Annual tuition for grade ' . $grade,
                        'tuition_fee' => $amount,
                        'total_amount' => $amount,
                        'is_active' => true,
                        'created_by' => $creator,
                    ]
                );
                $tStruct++;

                // 3) Payments — a few students in this grade pay (some today, some earlier; full/partial)
                $students = DB::table('students')->where('branch_id', $branchId)->where('grade', $grade)
                    ->whereNull('deleted_at')->orderBy('id')->limit(3)->pluck('user_id')->all();

                foreach ($students as $i => $userId) {
                    // Skip if this student already has a payment for this structure (idempotent)
                    $exists = FeePayment::where('student_id', $userId)->where('fee_structure_id', $structure->id)->exists();
                    if ($exists) { continue; }

                    $full = $i === 0;                  // student 0 pays in full today
                    $partial = $i === 1;               // student 1 partial today
                    $earlier = $i === 2;               // student 2 paid earlier in full
                    $paid = $full ? $amount : ($partial ? round($amount * 0.4, 2) : $amount);
                    $method = ['Cash', 'Card', 'Online'][$i % 3];
                    $date = $earlier ? (substr($ayName, 0, 4) . '-07-10') : $today;

                    FeePayment::create([
                        'fee_structure_id' => $structure->id,
                        'student_id' => $userId,
                        'branch_id' => $branchId,
                        'school_id' => $schoolId,
                        'amount_paid' => $paid,
                        'total_amount' => $paid,
                        'payment_date' => $date,
                        'payment_method' => $method,
                        'discount_amount' => 0,
                        'late_fee' => 0,
                        'payment_status' => $partial ? 'Partial' : 'Completed',
                        'academic_year' => $ayName,
                        'academic_year_id' => $ayId,
                        'remarks' => $partial ? 'First installment' : 'Full payment',
                        'created_by' => $creator,
                    ]);
                    $tPay++;
                }
            }

            $this->command?->info("Branch {$branchId}: fee types + {$grades->count()} structures + payments seeded.");
        }

        $this->command?->info("Done. ~{$tStruct} structures, ~{$tPay} payments (some dated today: {$today}).");
    }
}
