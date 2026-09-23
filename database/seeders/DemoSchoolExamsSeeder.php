<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamTerm;
use Database\Seeders\Concerns\ResolvesDemoSchoolContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exam terms, exams, schedules, marks, and results for Green Valley students.
 *
 * Depends on DemoSchoolSubjectsSeeder.
 *
 * php artisan db:seed --class=DemoSchoolExamsSeeder
 */
class DemoSchoolExamsSeeder extends Seeder
{
    use ResolvesDemoSchoolContext;

    public function run(): void
    {
        $ctx = $this->resolveDemoSchoolContext();
        if (! $ctx) {
            return;
        }

        $subjectCount = DB::table('subjects')
            ->where('branch_id', $ctx->branch->id)
            ->whereNull('deleted_at')
            ->count();

        if ($subjectCount === 0) {
            $this->command?->warn('No demo subjects found. Run DemoSchoolSubjectsSeeder first.');

            return;
        }

        $enteredBy = $ctx->branchAdmin?->id ?? $ctx->superAdmin?->id;
        $year = substr((string) $ctx->ay->name, 0, 4);

        $term = ExamTerm::firstOrCreate(
            ['branch_id' => $ctx->branch->id, 'code' => 'GV-T1-'.$year],
            [
                'name' => 'Term 1',
                'academic_year' => $ctx->ay->name,
                'school_id' => $ctx->school->id,
                'start_date' => $year.'-04-01',
                'end_date' => $year.'-09-30',
                'weightage' => 40,
                'description' => 'First term for Green Valley Demo School',
                'is_active' => true,
            ]
        );

        $markCount = 0;
        $scheduleCount = 0;

        foreach (['1', '2'] as $grade) {
            $exam = Exam::firstOrCreate(
                ['branch_id' => $ctx->branch->id, 'exam_term_id' => $term->id, 'name' => 'Term 1 Examination - Grade '.$grade],
                [
                    'school_id' => $ctx->school->id,
                    'exam_type' => 'Midterm',
                    'academic_year' => $ctx->ay->name,
                    'academic_year_id' => $ctx->ay->id,
                    'start_date' => $year.'-09-01',
                    'end_date' => $year.'-09-10',
                    'total_marks' => 100,
                    'passing_marks' => 35,
                    'description' => 'Term 1 midterm for Grade '.$grade,
                    'created_by' => $enteredBy,
                    'is_active' => true,
                ]
            );

            if (Schema::hasColumn('exams', 'grade_level')) {
                DB::table('exams')->where('id', $exam->id)->update(['grade_level' => $grade]);
            }

            $subjects = DB::table('subjects')
                ->where('branch_id', $ctx->branch->id)
                ->where('grade_level', $grade)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'name', 'teacher_id']);

            $day = 1;
            foreach ($subjects as $subject) {
                foreach (['A', 'B', 'C'] as $section) {
                    $students = DB::table('students')
                        ->where('branch_id', $ctx->branch->id)
                        ->where('grade', $grade)
                        ->where('section', $section)
                        ->where('student_status', 'Active')
                        ->whereNull('deleted_at')
                        ->get(['user_id']);

                    if ($students->isEmpty()) {
                        continue;
                    }

                    $schedule = ExamSchedule::firstOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'subject_id' => $subject->id,
                            'grade' => $grade,
                            'section' => $section,
                        ],
                        [
                            'branch_id' => $ctx->branch->id,
                            'exam_date' => $year.'-09-'.str_pad((string) min($day, 9), 2, '0', STR_PAD_LEFT),
                            'start_time' => '09:30:00',
                            'end_time' => '12:30:00',
                            'duration' => 180,
                            'total_marks' => 100,
                            'passing_marks' => 35,
                            'room_number' => 'R-'.$grade.$section,
                            'invigilator_id' => $subject->teacher_id,
                            'instructions' => 'Bring your own stationery.',
                        ]
                    );
                    DB::table('exam_schedules')->where('id', $schedule->id)->update([
                        'status' => 'Completed',
                        'is_active' => true,
                    ]);
                    $scheduleCount++;

                    foreach ($students as $i => $student) {
                        $isAbsent = ($i % 12 === 11);
                        $marks = $isAbsent ? 0 : (38 + (($i * 11 + (int) $subject->id * 5) % 57));
                        $pct = $marks;
                        $existing = DB::table('exam_marks')
                            ->where('exam_schedule_id', $schedule->id)
                            ->where('student_id', $student->user_id)
                            ->first();
                        $payload = [
                            'subject_id' => $subject->id,
                            'marks_obtained' => $marks,
                            'total_marks' => 100,
                            'percentage' => $pct,
                            'grade' => $this->letterGrade($pct),
                            'is_absent' => $isAbsent,
                            'is_pass' => ! $isAbsent && $marks >= 35,
                            'remarks' => $isAbsent ? 'Absent - medical leave' : ($marks >= 75 ? 'Excellent work' : 'Good effort'),
                            'status' => 'Published',
                            'entered_by' => $enteredBy,
                            'approved_by' => $enteredBy,
                            'approved_at' => now(),
                            'updated_at' => now(),
                        ];
                        if ($existing) {
                            DB::table('exam_marks')->where('id', $existing->id)->update($payload);
                        } else {
                            DB::table('exam_marks')->insert(array_merge($payload, [
                                'exam_schedule_id' => $schedule->id,
                                'student_id' => $student->user_id,
                                'created_at' => now(),
                            ]));
                        }
                        $markCount++;
                    }
                }
                $day++;
            }

            $studentsForGrade = DB::table('students')
                ->where('branch_id', $ctx->branch->id)
                ->where('grade', $grade)
                ->where('student_status', 'Active')
                ->whereNull('deleted_at')
                ->get(['user_id']);

            foreach ($studentsForGrade as $student) {
                $agg = DB::table('exam_marks')
                    ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
                    ->where('exam_schedules.exam_id', $exam->id)
                    ->where('exam_marks.student_id', $student->user_id)
                    ->selectRaw('SUM(exam_marks.marks_obtained) obtained, SUM(exam_marks.total_marks) total')
                    ->first();

                $obtained = (float) ($agg->obtained ?? 0);
                $total = (float) ($agg->total ?? 0);
                $pct = $total > 0 ? round($obtained / $total * 100, 2) : 0;

                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $student->user_id],
                    [
                        'marks_obtained' => $obtained,
                        'grade' => $this->letterGrade($pct),
                        'percentage' => $pct,
                        'is_pass' => $pct >= 35,
                    ]
                );
            }
        }

        $this->command?->info("Demo exams: 1 term, 2 exams, {$scheduleCount} schedules, {$markCount} marks.");
    }

    private function letterGrade(float $pct): string
    {
        if ($pct >= 90) {
            return 'A+';
        }
        if ($pct >= 80) {
            return 'A';
        }
        if ($pct >= 70) {
            return 'B+';
        }
        if ($pct >= 60) {
            return 'B';
        }
        if ($pct >= 50) {
            return 'C';
        }
        if ($pct >= 40) {
            return 'D';
        }

        return 'F';
    }
}
