<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStudentEnrollments extends Command
{
    protected $signature = 'enrollments:backfill {--dry-run : Do not write, only report} {--only-missing : Only create enrollments that do not exist}';

    protected $description = 'Backfill student_enrollments from students legacy grade/section/academic_year fields';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');

        $students = Student::query()
            ->select(['id', 'user_id', 'school_id', 'branch_id', 'grade', 'section', 'roll_number', 'academic_year', 'academic_year_id'])
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;
        $noYear = 0;

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                $academicYearId = $student->academic_year_id;

                if (!$academicYearId && $student->academic_year) {
                    $academicYearId = AcademicYear::query()
                        ->where('name', $student->academic_year)
                        ->value('id');
                }

                if (!$academicYearId) {
                    $noYear++;
                    $skipped++;
                    continue;
                }

                $exists = StudentEnrollment::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYearId)
                    ->exists();

                if ($exists && $onlyMissing) {
                    $skipped++;
                    continue;
                }

                if ($exists) {
                    // If not only-missing, keep existing and skip to avoid overwriting.
                    $skipped++;
                    continue;
                }

                $payload = [
                    'student_id' => $student->id,
                    'school_id' => $student->school_id,
                    'branch_id' => $student->branch_id,
                    'academic_year_id' => $academicYearId,
                    'grade' => (string) $student->grade,
                    'section' => $student->section,
                    'roll_number' => $student->roll_number ? (string) $student->roll_number : null,
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (!$dryRun) {
                    StudentEnrollment::query()->create($payload);
                }
                $created++;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Students scanned: {$students->count()}");
        $this->info("Enrollments created: {$created}" . ($dryRun ? " (dry-run)" : ""));
        $this->info("Skipped: {$skipped}");
        $this->info("Students missing academic year mapping: {$noYear}");

        return self::SUCCESS;
    }
}

