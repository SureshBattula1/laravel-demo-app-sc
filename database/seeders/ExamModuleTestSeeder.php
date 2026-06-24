<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test data for the Exam module.
 *
 * Run with:  php artisan db:seed --class=ExamModuleTestSeeder
 *
 * Idempotent. Builds the full chain for branches that already have subjects/sections/students:
 *   exam_term -> exam -> exam_schedule (per subject) -> exam_marks (per student) -> exam_results
 *
 * Depends on SubjectModuleTestSeeder having run (subjects in grade 1).
 */
class ExamModuleTestSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::current()->first() ?? AcademicYear::orderBy('id')->first();
        $ayId = $year?->id;
        $ayName = $year?->name ?? (date('Y') . '-' . (date('Y') + 1));

        // Branches that have at least one subject to schedule.
        $branchIds = DB::table('subjects')->whereNull('deleted_at')->distinct()->pluck('branch_id');

        if ($branchIds->isEmpty()) {
            $this->command?->warn('No subjects found — run SubjectModuleTestSeeder first.');
            return;
        }

        foreach ($branchIds as $branchId) {
            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id');

            // Pick a grade that has both subjects AND students in this branch.
            $gradeValue = DB::table('subjects')->where('branch_id', $branchId)->whereNull('deleted_at')->value('grade_level');
            $subjects = DB::table('subjects')
                ->where('branch_id', $branchId)
                ->where('grade_level', $gradeValue)
                ->whereNull('deleted_at')
                ->get(['id', 'name']);

            // Students of that branch + grade (any section), grouped by section.
            $students = DB::table('students')
                ->where('branch_id', $branchId)
                ->where('grade', $gradeValue)
                ->whereNull('deleted_at')
                ->get(['user_id', 'section']);

            if ($subjects->isEmpty() || $students->isEmpty()) {
                $this->command?->warn("Branch {$branchId}: no subjects/students for grade {$gradeValue} — skipping.");
                continue;
            }

            $section = $students->first()->section ?: 'A';
            $sectionStudents = $students->where('section', $section)->values();
            if ($sectionStudents->isEmpty()) {
                $sectionStudents = $students->values();
            }

            $enteredBy = DB::table('users')->where('branch_id', $branchId)->value('id')
                ?? DB::table('users')->value('id');

            // 1) Exam term
            $term = ExamTerm::firstOrCreate(
                ['branch_id' => $branchId, 'code' => "T1-B{$branchId}-" . substr($ayName, 0, 4)],
                [
                    'name' => 'Term 1',
                    'academic_year' => $ayName,
                    'school_id' => $schoolId,
                    'start_date' => substr($ayName, 0, 4) . '-04-01',
                    'end_date' => substr($ayName, 0, 4) . '-09-30',
                    'weightage' => 40,
                    'is_active' => true,
                ]
            );

            // 2) Exam (with real total/passing marks so statistics works)
            $exam = Exam::firstOrCreate(
                ['branch_id' => $branchId, 'exam_term_id' => $term->id, 'name' => 'Term 1 Examination'],
                [
                    'school_id' => $schoolId,
                    'exam_type' => 'Midterm',
                    'grade_level' => $gradeValue,
                    'section' => $section,
                    'start_date' => substr($ayName, 0, 4) . '-09-01',
                    'end_date' => substr($ayName, 0, 4) . '-09-10',
                    'total_marks' => 100,
                    'passing_marks' => 35,
                    'academic_year' => $ayName,
                    'academic_year_id' => $ayId,
                    'created_by' => $enteredBy,
                    'is_active' => true,
                ]
            );

            $day = 1;
            foreach ($subjects as $subject) {
                // 3) Schedule per subject
                $schedule = ExamSchedule::firstOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'subject_id' => $subject->id,
                        'grade' => (string) $gradeValue,
                        'section' => $section,
                    ],
                    [
                        'branch_id' => $branchId,
                        'exam_date' => substr($ayName, 0, 4) . '-09-0' . min($day, 9),
                        'start_time' => '09:30:00',
                        'end_time' => '12:30:00',
                        'duration' => 180,
                        'total_marks' => 100,
                        'passing_marks' => 35,
                        'room_number' => 'R' . (100 + $day),
                        'instructions' => 'Bring your own stationery.',
                        'status' => 'Scheduled',
                        'is_active' => true,
                    ]
                );
                $day++;

                // 4) Marks per student (deterministic spread; one absentee)
                foreach ($sectionStudents as $i => $stu) {
                    $isAbsent = ($i % 7 === 6);
                    $marks = $isAbsent ? 0 : (35 + (($i * 13 + $subject->id * 7) % 60)); // 35..94
                    $pct = $marks; // total 100
                    ExamMark::updateOrCreate(
                        ['exam_schedule_id' => $schedule->id, 'student_id' => $stu->user_id],
                        [
                            'subject_id' => $subject->id,
                            'marks_obtained' => $marks,
                            'total_marks' => 100,
                            'percentage' => $pct,
                            'grade' => $this->grade($pct),
                            'is_absent' => $isAbsent,
                            'is_pass' => !$isAbsent && $marks >= 35,
                            'remarks' => $isAbsent
                                ? 'Absent - medical leave'
                                : ($marks >= 75 ? 'Excellent work, keep it up' : ($marks >= 35 ? 'Good effort, can improve' : 'Needs improvement, see teacher')),
                            'status' => 'Published',
                            'entered_by' => $enteredBy,
                        ]
                    );
                }
            }

            // 5) Overall exam_results per student (legacy/statistics path) — aggregate across subjects
            foreach ($sectionStudents as $stu) {
                $agg = DB::table('exam_marks')
                    ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
                    ->where('exam_schedules.exam_id', $exam->id)
                    ->where('exam_marks.student_id', $stu->user_id)
                    ->selectRaw('SUM(marks_obtained) obtained, SUM(exam_marks.total_marks) total')
                    ->first();

                $obtained = (float) ($agg->obtained ?? 0);
                $total = (float) ($agg->total ?? 0);
                $pct = $total > 0 ? round($obtained / $total * 100, 2) : 0;

                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $stu->user_id],
                    [
                        'marks_obtained' => $obtained,
                        'grade' => $this->grade($pct),
                        'percentage' => $pct,
                        'is_pass' => $obtained >= $exam->passing_marks,
                    ]
                );
            }

            $this->command?->info("Branch {$branchId}: term+exam+{$subjects->count()} schedules+marks for grade {$gradeValue}-{$section}.");
        }

        $this->command?->info('Exam module test data seeded.');
    }

    private function grade(float $pct): string
    {
        if ($pct >= 90) return 'A+';
        if ($pct >= 80) return 'A';
        if ($pct >= 70) return 'B+';
        if ($pct >= 60) return 'B';
        if ($pct >= 50) return 'C';
        if ($pct >= 40) return 'D';
        return 'F';
    }
}
