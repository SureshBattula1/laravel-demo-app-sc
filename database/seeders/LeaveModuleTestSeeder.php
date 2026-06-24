<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test data for the Leaves module (student + teacher).
 *
 * Run with:  php artisan db:seed --class=LeaveModuleTestSeeder
 *
 * Idempotent (updateOrInsert keyed on user + from_date). Creates leaves with a mix of
 * statuses, types and date ranges across a few branches, with branch_id / school_id /
 * academic_year_id populated so they appear in the admin list AND the student/teacher tabs.
 */
class LeaveModuleTestSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::current()->first() ?? AcademicYear::orderBy('id')->first();
        $ayId = $year?->id;
        $yr = $year ? substr($year->name, 0, 4) : (string) date('Y');

        $studentHasAy = Schema::hasColumn('student_leaves', 'academic_year_id');
        $teacherHasAy = Schema::hasColumn('teacher_leaves', 'academic_year_id');
        $now = now();

        $branchIds = DB::table('branches')->whereNull('deleted_at')->where('is_active', 1)->orderBy('id')->limit(3)->pluck('id');

        $studentPlan = [
            ['type' => 'Sick Leave',    'status' => 'Approved', 'from' => "-08-04", 'to' => "-08-05", 'reason' => 'Fever and cold', 'remarks' => 'Approved by class teacher'],
            ['type' => 'Casual Leave',  'status' => 'Pending',  'from' => "-08-11", 'to' => "-08-11", 'reason' => 'Family function', 'remarks' => null],
            ['type' => 'Medical Leave', 'status' => 'Rejected', 'from' => "-08-18", 'to' => "-08-20", 'reason' => 'Surgery recovery', 'remarks' => 'Insufficient documents'],
        ];
        $teacherPlan = [
            ['type' => 'Casual Leave', 'status' => 'Approved', 'from' => "-08-06", 'to' => "-08-06", 'reason' => 'Personal work', 'remarks' => 'Substitute arranged', 'sub' => true],
            ['type' => 'Sick Leave',   'status' => 'Pending',  'from' => "-08-13", 'to' => "-08-14", 'reason' => 'Viral fever', 'remarks' => null, 'sub' => false],
        ];

        $totalS = 0; $totalT = 0;
        foreach ($branchIds as $branchId) {
            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id');
            $approver = DB::table('users')->where('branch_id', $branchId)->where('role', 'BranchAdmin')->value('id')
                ?? DB::table('users')->where('branch_id', $branchId)->value('id');

            $students = DB::table('users')->where('branch_id', $branchId)->where('role', 'Student')->orderBy('id')->limit(3)->pluck('id')->all();
            $teachers = DB::table('users')->where('branch_id', $branchId)->where('role', 'Teacher')->orderBy('id')->limit(3)->pluck('id')->all();

            foreach ($studentPlan as $i => $p) {
                if (!isset($students[$i])) { continue; }
                $from = $yr . $p['from']; $to = $yr . $p['to'];
                $row = [
                    'branch_id' => $branchId,
                    'from_date' => $from,
                    'to_date' => $to,
                    'total_days' => $this->days($from, $to),
                    'leave_type' => $p['type'],
                    'status' => $p['status'],
                    'reason' => $p['reason'],
                    'remarks' => $p['remarks'],
                    'approved_by' => $p['status'] === 'Approved' ? $approver : null,
                    'approved_at' => $p['status'] === 'Approved' ? $now : null,
                    'created_by' => $approver,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
                if ($studentHasAy) { $row['academic_year_id'] = $ayId; }
                if (Schema::hasColumn('student_leaves', 'school_id')) { $row['school_id'] = $schoolId; }
                DB::table('student_leaves')->updateOrInsert(
                    ['student_id' => $students[$i], 'from_date' => $from],
                    $row
                );
                $totalS++;
            }

            foreach ($teacherPlan as $i => $p) {
                if (!isset($teachers[$i])) { continue; }
                $from = $yr . $p['from']; $to = $yr . $p['to'];
                $row = [
                    'branch_id' => $branchId,
                    'from_date' => $from,
                    'to_date' => $to,
                    'total_days' => $this->days($from, $to),
                    'leave_type' => $p['type'],
                    'status' => $p['status'],
                    'reason' => $p['reason'],
                    'remarks' => $p['remarks'],
                    'substitute_teacher_id' => $p['sub'] && isset($teachers[2]) ? $teachers[2] : null,
                    'approved_by' => $p['status'] === 'Approved' ? $approver : null,
                    'approved_at' => $p['status'] === 'Approved' ? $now : null,
                    'created_by' => $approver,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
                if ($teacherHasAy) { $row['academic_year_id'] = $ayId; }
                if (Schema::hasColumn('teacher_leaves', 'school_id')) { $row['school_id'] = $schoolId; }
                DB::table('teacher_leaves')->updateOrInsert(
                    ['teacher_id' => $teachers[$i], 'from_date' => $from],
                    $row
                );
                $totalT++;
            }

            $this->command?->info("Branch {$branchId}: student + teacher leaves seeded.");
        }

        $this->command?->info("Done. ~{$totalS} student leaves, ~{$totalT} teacher leaves across " . $branchIds->count() . ' branch(es).');
    }

    private function days(string $from, string $to): int
    {
        $f = \Carbon\Carbon::parse($from)->startOfDay();
        $t = \Carbon\Carbon::parse($to)->startOfDay();
        return max(1, $f->diffInDays($t) + 1);
    }
}
