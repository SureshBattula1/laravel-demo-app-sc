<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesDemoSchoolContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student and teacher leaves for Green Valley Demo School users.
 *
 * php artisan db:seed --class=DemoSchoolLeavesSeeder
 */
class DemoSchoolLeavesSeeder extends Seeder
{
    use ResolvesDemoSchoolContext;

    public function run(): void
    {
        $ctx = $this->resolveDemoSchoolContext();
        if (! $ctx) {
            return;
        }

        $ayId = $ctx->ay->id;
        $year = substr((string) $ctx->ay->name, 0, 4);
        $approver = $ctx->branchAdmin?->id ?? $ctx->superAdmin?->id;
        $now = now();

        $studentLeaves = [
            ['email' => 'student.g1a.01@greenvalley.demo', 'type' => 'Sick Leave', 'status' => 'Approved', 'from' => $year.'-08-04', 'to' => $year.'-08-05', 'reason' => 'Fever and cold'],
            ['email' => 'student.g1a.02@greenvalley.demo', 'type' => 'Casual Leave', 'status' => 'Pending', 'from' => $year.'-08-11', 'to' => $year.'-08-11', 'reason' => 'Family function'],
            ['email' => 'student.g1b.01@greenvalley.demo', 'type' => 'Medical Leave', 'status' => 'Rejected', 'from' => $year.'-08-18', 'to' => $year.'-08-20', 'reason' => 'Surgery recovery', 'remarks' => 'Insufficient documents'],
            ['email' => 'student.g1c.05@greenvalley.demo', 'type' => 'Family Emergency', 'status' => 'Approved', 'from' => $year.'-09-02', 'to' => $year.'-09-03', 'reason' => 'Grandparent hospitalization'],
            ['email' => 'student.g2a.01@greenvalley.demo', 'type' => 'Sick Leave', 'status' => 'Approved', 'from' => $year.'-08-25', 'to' => $year.'-08-26', 'reason' => 'Viral fever'],
            ['email' => 'student.g2b.03@greenvalley.demo', 'type' => 'Casual Leave', 'status' => 'Pending', 'from' => $year.'-09-08', 'to' => $year.'-09-08', 'reason' => 'Sibling wedding'],
            ['email' => 'student.g2c.10@greenvalley.demo', 'type' => 'Other', 'status' => 'Cancelled', 'from' => $year.'-07-14', 'to' => $year.'-07-14', 'reason' => 'Travel cancelled'],
            ['email' => 'student.g2a.12@greenvalley.demo', 'type' => 'Medical Leave', 'status' => 'Approved', 'from' => $year.'-09-10', 'to' => $year.'-09-12', 'reason' => 'Dental procedure'],
        ];

        $teacherLeaves = [
            ['email' => 'teacher01@greenvalley.demo', 'type' => 'Casual Leave', 'status' => 'Approved', 'from' => $year.'-08-06', 'to' => $year.'-08-06', 'reason' => 'Personal work', 'sub' => 'teacher09@greenvalley.demo'],
            ['email' => 'teacher03@greenvalley.demo', 'type' => 'Sick Leave', 'status' => 'Pending', 'from' => $year.'-08-13', 'to' => $year.'-08-14', 'reason' => 'Viral fever'],
            ['email' => 'teacher04@greenvalley.demo', 'type' => 'Medical Leave', 'status' => 'Approved', 'from' => $year.'-07-21', 'to' => $year.'-07-22', 'reason' => 'Medical checkup'],
            ['email' => 'teacher07@greenvalley.demo', 'type' => 'Casual Leave', 'status' => 'Rejected', 'from' => $year.'-09-04', 'to' => $year.'-09-04', 'reason' => 'Personal travel', 'remarks' => 'Exam week'],
            ['email' => 'teacher08@greenvalley.demo', 'type' => 'Unpaid Leave', 'status' => 'Approved', 'from' => $year.'-09-16', 'to' => $year.'-09-17', 'reason' => 'Family function', 'sub' => 'teacher05@greenvalley.demo'],
        ];

        $studentCount = 0;
        foreach ($studentLeaves as $row) {
            $userId = DB::table('users')->where('email', $row['email'])->value('id');
            if (! $userId) {
                continue;
            }

            $payload = [
                'branch_id' => $ctx->branch->id,
                'to_date' => $row['to'],
                'total_days' => $this->days($row['from'], $row['to']),
                'leave_type' => $row['type'],
                'status' => $row['status'],
                'reason' => $row['reason'],
                'remarks' => $row['remarks'] ?? null,
                'approved_by' => $row['status'] === 'Approved' ? $approver : null,
                'approved_at' => $row['status'] === 'Approved' ? $now : null,
                'created_by' => $approver,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('student_leaves', 'school_id')) {
                $payload['school_id'] = $ctx->school->id;
            }
            if (Schema::hasColumn('student_leaves', 'academic_year_id')) {
                $payload['academic_year_id'] = $ayId;
            }

            $existing = DB::table('student_leaves')->where('student_id', $userId)->where('from_date', $row['from'])->first();
            if ($existing) {
                DB::table('student_leaves')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('student_leaves')->insert(array_merge($payload, [
                    'student_id' => $userId,
                    'from_date' => $row['from'],
                    'created_at' => $now,
                ]));
            }
            $studentCount++;
        }

        $teacherCount = 0;
        foreach ($teacherLeaves as $row) {
            $userId = DB::table('users')->where('email', $row['email'])->value('id');
            if (! $userId) {
                continue;
            }

            $payload = [
                'branch_id' => $ctx->branch->id,
                'to_date' => $row['to'],
                'total_days' => $this->days($row['from'], $row['to']),
                'leave_type' => $row['type'],
                'status' => $row['status'],
                'reason' => $row['reason'],
                'remarks' => $row['remarks'] ?? null,
                'substitute_teacher_id' => isset($row['sub']) ? DB::table('users')->where('email', $row['sub'])->value('id') : null,
                'approved_by' => $row['status'] === 'Approved' ? $approver : null,
                'approved_at' => $row['status'] === 'Approved' ? $now : null,
                'created_by' => $approver,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('teacher_leaves', 'school_id')) {
                $payload['school_id'] = $ctx->school->id;
            }
            if (Schema::hasColumn('teacher_leaves', 'academic_year_id')) {
                $payload['academic_year_id'] = $ayId;
            }

            $existing = DB::table('teacher_leaves')->where('teacher_id', $userId)->where('from_date', $row['from'])->first();
            if ($existing) {
                DB::table('teacher_leaves')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('teacher_leaves')->insert(array_merge($payload, [
                    'teacher_id' => $userId,
                    'from_date' => $row['from'],
                    'created_at' => $now,
                ]));
            }
            $teacherCount++;
        }

        $this->command?->info("Demo leaves: {$studentCount} student leaves, {$teacherCount} teacher leaves.");
    }

    private function days(string $from, string $to): int
    {
        return max(1, Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) + 1);
    }
}
