<?php

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Transaction;
use Database\Seeders\Concerns\ResolvesDemoSchoolContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fee types, structures, payments, and account categories for Green Valley Demo School.
 *
 * php artisan db:seed --class=DemoSchoolFeesSeeder
 */
class DemoSchoolFeesSeeder extends Seeder
{
    use ResolvesDemoSchoolContext;

    public function run(): void
    {
        $ctx = $this->resolveDemoSchoolContext();
        if (! $ctx) {
            return;
        }

        $creator = $ctx->branchAdmin?->id ?? $ctx->superAdmin?->id;
        $today = now()->toDateString();
        $dueDate = substr((string) $ctx->ay->name, 0, 4).'-07-15';

        $types = [
            ['name' => 'Tuition Fee', 'code' => 'GV-TUI', 'mandatory' => true, 'amount' => ['1' => 42000, '2' => 43000]],
            ['name' => 'Transport Fee', 'code' => 'GV-TRA', 'mandatory' => false, 'amount' => ['1' => 12000, '2' => 12000]],
            ['name' => 'Exam Fee', 'code' => 'GV-EXM', 'mandatory' => true, 'amount' => ['1' => 2500, '2' => 2500]],
            ['name' => 'Library Fee', 'code' => 'GV-LIB', 'mandatory' => false, 'amount' => ['1' => 1500, '2' => 1500]],
        ];

        foreach ($types as $type) {
            FeeType::firstOrCreate(
                ['branch_id' => $ctx->branch->id, 'code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['name'].' for Green Valley Demo School',
                    'academic_year_id' => $ctx->ay->id,
                    'school_id' => $ctx->school->id,
                    'is_mandatory' => $type['mandatory'],
                    'is_refundable' => false,
                    'is_active' => true,
                ]
            );
        }

        $structures = [];
        foreach (['1', '2'] as $grade) {
            foreach ($types as $type) {
                $amount = $type['amount'][$grade];
                $structure = FeeStructure::firstOrCreate(
                    [
                        'branch_id' => $ctx->branch->id,
                        'grade' => $grade,
                        'fee_type' => $type['name'],
                        'academic_year' => $ctx->ay->name,
                    ],
                    [
                        'school_id' => $ctx->school->id,
                        'amount' => $amount,
                        'academic_year_id' => $ctx->ay->id,
                        'due_date' => $dueDate,
                        'description' => $type['name'].' for Grade '.$grade,
                        'tuition_fee' => $type['name'] === 'Tuition Fee' ? $amount : 0,
                        'exam_fee' => $type['name'] === 'Exam Fee' ? $amount : 0,
                        'library_fee' => $type['name'] === 'Library Fee' ? $amount : 0,
                        'transport_fee' => $type['name'] === 'Transport Fee' ? $amount : 0,
                        'total_amount' => $amount,
                        'is_active' => true,
                        'created_by' => $creator,
                    ]
                );
                $structures[$grade][$type['name']] = $structure;
            }
        }

        $paymentPlan = [
            ['email' => 'student.g1a.01@greenvalley.demo', 'grade' => '1', 'type' => 'Tuition Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Cash', 'date' => $today, 'remarks' => 'Full payment'],
            ['email' => 'student.g1a.02@greenvalley.demo', 'grade' => '1', 'type' => 'Tuition Fee', 'ratio' => 0.4, 'status' => 'Partial', 'method' => 'Online', 'date' => $today, 'remarks' => 'First installment'],
            ['email' => 'student.g1a.03@greenvalley.demo', 'grade' => '1', 'type' => 'Exam Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Card', 'date' => $today, 'remarks' => 'Exam fee paid'],
            ['email' => 'student.g1b.01@greenvalley.demo', 'grade' => '1', 'type' => 'Tuition Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Online', 'date' => substr($ctx->ay->name, 0, 4).'-07-10', 'remarks' => 'Paid at session start'],
            ['email' => 'student.g1c.01@greenvalley.demo', 'grade' => '1', 'type' => 'Library Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Cash', 'date' => $today, 'remarks' => 'Library fee'],
            ['email' => 'student.g2a.01@greenvalley.demo', 'grade' => '2', 'type' => 'Tuition Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Bank Transfer', 'date' => $today, 'remarks' => 'Full payment'],
            ['email' => 'student.g2a.02@greenvalley.demo', 'grade' => '2', 'type' => 'Tuition Fee', 'ratio' => 0.5, 'status' => 'Partial', 'method' => 'Cheque', 'date' => $today, 'remarks' => 'First installment'],
            ['email' => 'student.g2b.01@greenvalley.demo', 'grade' => '2', 'type' => 'Transport Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Online', 'date' => $today, 'remarks' => 'Annual transport'],
            ['email' => 'student.g2c.01@greenvalley.demo', 'grade' => '2', 'type' => 'Exam Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Cash', 'date' => substr($ctx->ay->name, 0, 4).'-08-01', 'remarks' => 'Exam fee'],
            ['email' => 'student.g2a.05@greenvalley.demo', 'grade' => '2', 'type' => 'Library Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Card', 'date' => $today, 'remarks' => 'Library fee'],
            ['email' => 'student.g1b.05@greenvalley.demo', 'grade' => '1', 'type' => 'Transport Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Online', 'date' => $today, 'remarks' => 'Transport'],
            ['email' => 'student.g2c.08@greenvalley.demo', 'grade' => '2', 'type' => 'Tuition Fee', 'ratio' => 1, 'status' => 'Completed', 'method' => 'Cash', 'date' => substr($ctx->ay->name, 0, 4).'-07-20', 'remarks' => 'Full tuition'],
        ];

        $payments = 0;
        foreach ($paymentPlan as $row) {
            $userId = DB::table('users')->where('email', $row['email'])->value('id');
            $structure = $structures[$row['grade']][$row['type']] ?? null;
            if (! $userId || ! $structure) {
                continue;
            }
            if (FeePayment::where('student_id', $userId)->where('fee_structure_id', $structure->id)->exists()) {
                continue;
            }

            $amount = round(((float) $structure->amount) * $row['ratio'], 2);
            FeePayment::create([
                'fee_structure_id' => $structure->id,
                'student_id' => $userId,
                'branch_id' => $ctx->branch->id,
                'school_id' => $ctx->school->id,
                'amount_paid' => $amount,
                'total_amount' => $amount,
                'payment_date' => $row['date'],
                'payment_method' => $row['method'],
                'discount_amount' => 0,
                'late_fee' => 0,
                'payment_status' => $row['status'],
                'academic_year' => $ctx->ay->name,
                'academic_year_id' => $ctx->ay->id,
                'remarks' => $row['remarks'],
                'created_by' => $creator,
            ]);
            $payments++;
        }

        $categories = [
            ['name' => 'Tuition Income', 'code' => 'GV-INC-TUI', 'type' => 'Income', 'sub' => 'Operating'],
            ['name' => 'Donation', 'code' => 'GV-INC-DON', 'type' => 'Income', 'sub' => 'Other'],
            ['name' => 'Staff Salaries', 'code' => 'GV-EXP-SAL', 'type' => 'Expense', 'sub' => 'Payroll'],
            ['name' => 'Utilities', 'code' => 'GV-EXP-UTL', 'type' => 'Expense', 'sub' => 'Operating'],
            ['name' => 'Maintenance', 'code' => 'GV-EXP-MNT', 'type' => 'Expense', 'sub' => 'Operating'],
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            $record = AccountCategory::firstOrCreate(
                ['school_id' => $ctx->school->id, 'code' => $cat['code']],
                [
                    'branch_id' => $ctx->branch->id,
                    'academic_year_id' => $ctx->ay->id,
                    'name' => $cat['name'],
                    'type' => $cat['type'],
                    'sub_type' => $cat['sub'],
                    'description' => $cat['name'].' for Green Valley Demo School',
                    'is_active' => true,
                ]
            );
            $catIds[$cat['code']] = $record;
        }

        $month = now()->format('F');
        $fy = $ctx->ay->name;
        $txPlan = [
            ['code' => 'GV-INC-TUI', 'amount' => 250000, 'status' => 'Approved', 'method' => 'Bank Transfer', 'party' => 'Fee Collection', 'number' => 'GV-INC-001'],
            ['code' => 'GV-INC-DON', 'amount' => 50000, 'status' => 'Approved', 'method' => 'UPI', 'party' => 'Alumni Trust', 'number' => 'GV-INC-002'],
            ['code' => 'GV-EXP-SAL', 'amount' => 180000, 'status' => 'Approved', 'method' => 'Bank Transfer', 'party' => 'Payroll', 'number' => 'GV-EXP-001'],
            ['code' => 'GV-EXP-UTL', 'amount' => 24000, 'status' => 'Pending', 'method' => 'Check', 'party' => 'Power Board', 'number' => 'GV-EXP-002'],
        ];

        foreach ($txPlan as $tx) {
            $category = $catIds[$tx['code']];
            Transaction::firstOrCreate(
                ['transaction_number' => $tx['number']],
                [
                    'branch_id' => $ctx->branch->id,
                    'school_id' => $ctx->school->id,
                    'category_id' => $category->id,
                    'transaction_date' => $today,
                    'type' => $category->type,
                    'amount' => $tx['amount'],
                    'party_name' => $tx['party'],
                    'party_type' => $category->type === 'Income' ? 'Customer' : 'Vendor',
                    'payment_method' => $tx['method'],
                    'description' => $tx['party'].' - '.$category->name,
                    'status' => $tx['status'],
                    'created_by' => $creator,
                    'approved_by' => $tx['status'] === 'Approved' ? $creator : null,
                    'approved_at' => $tx['status'] === 'Approved' ? now() : null,
                    'financial_year' => $fy,
                    'month' => $month,
                ]
            );
        }

        $this->command?->info('Demo fees: 4 types, 8 structures, '.$payments.' payments, 5 account categories.');
    }
}
