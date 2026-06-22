<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeds student_attendance and teacher_attendance for the last N days (incl. today)
 * with a realistic status mix, so the attendance lists and dashboard tabs have data.
 * Idempotent: updateOrInsert keyed on (student_id|teacher_id, date).
 */
class AttendanceBackfillSeeder extends Seeder
{
    private const DAYS = 20;

    public function run(): void
    {
        $now = Carbon::now();
        $ay = DB::table('academic_years')->where('is_current', 1)->first()
            ?? DB::table('academic_years')->orderBy('id')->first();
        $ayId = $ay->id ?? null;
        $ayName = $ay->name ?? null;
        $markedBy = 'seeder@system';

        // Build the date window (today back DAYS-1 days), skipping Sundays.
        $dates = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $d = Carbon::today()->subDays($i);
            if ($d->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }
            $dates[] = $d->toDateString();
        }

        // ---- STUDENTS ----
        $students = DB::table('students')
            ->where('student_status', 'Active')
            ->whereNotNull('grade')
            ->get(['id', 'user_id', 'branch_id', 'school_id', 'grade', 'section']);

        $sCount = 0;
        foreach ($students as $idx => $s) {
            foreach ($dates as $dayOffset => $date) {
                $status = $this->pickStatus(($s->user_id * 7) + ($dayOffset * 13), true);
                DB::table('student_attendance')->updateOrInsert(
                    ['student_id' => $s->user_id, 'date' => $date],
                    [
                        'branch_id'        => $s->branch_id,
                        'school_id'        => $s->school_id,
                        'grade_level'      => (string) $s->grade,
                        'section'          => (string) ($s->section ?: 'A'),
                        'status'           => $status,
                        'academic_year_id' => $ayId,
                        'academic_year'    => $ayName,
                        'marked_by'        => $markedBy,
                        'updated_at'       => $now,
                        'created_at'       => $now,
                    ]
                );
                $sCount++;
            }
        }

        // ---- TEACHERS ----
        $teachers = DB::table('teachers')
            ->where('teacher_status', 'Active')
            ->get(['id', 'user_id', 'branch_id', 'school_id']);

        $tCount = 0;
        foreach ($teachers as $t) {
            foreach ($dates as $dayOffset => $date) {
                $status = $this->pickStatus(($t->user_id * 11) + ($dayOffset * 5), false);
                DB::table('teacher_attendance')->updateOrInsert(
                    ['teacher_id' => $t->user_id, 'date' => $date],
                    [
                        'branch_id'        => $t->branch_id,
                        'school_id'        => $t->school_id,
                        'status'           => $status,
                        'academic_year_id' => $ayId,
                        'marked_by'        => $markedBy,
                        'updated_at'       => $now,
                        'created_at'       => $now,
                    ]
                );
                $tCount++;
            }
        }

        $this->command->info("✅ Attendance backfill complete over " . count($dates) . " days.");
        $this->command->info("   Student rows: {$sCount} ({$students->count()} students)");
        $this->command->info("   Teacher rows: {$tCount} ({$teachers->count()} teachers)");
    }

    /** Deterministic status by a seed value; students ~85% present, teachers ~92%. */
    private function pickStatus(int $seed, bool $isStudent): string
    {
        $p = $seed % 100;
        if ($isStudent) {
            if ($p < 85) return 'Present';
            if ($p < 90) return 'Absent';
            if ($p < 94) return 'Late';
            if ($p < 97) return 'Leave';
            return 'Half-Day';
        }
        if ($p < 92) return 'Present';
        if ($p < 95) return 'Absent';
        if ($p < 98) return 'Late';
        return 'Leave';
    }
}
