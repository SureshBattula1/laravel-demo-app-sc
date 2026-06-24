<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SectionSubject;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test data for the Subject module.
 *
 * Run with:  php artisan db:seed --class=SubjectModuleTestSeeder
 *
 * Idempotent (firstOrCreate). Creates the same subject codes across several branches
 * to exercise per-branch code uniqueness, and assigns subjects to sections with a
 * populated academic_year_id so they appear in the default (context-scoped) list.
 */
class SubjectModuleTestSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::current()->first() ?? AcademicYear::orderBy('id')->first();
        $ayId = $year?->id;
        $ayName = $year?->name ?? (date('Y') . '-' . (date('Y') + 1));

        $catalog = [
            ['code' => 'MATH101', 'name' => 'Mathematics',        'type' => 'Core',     'credits' => 4],
            ['code' => 'ENG101',  'name' => 'English',            'type' => 'Language', 'credits' => 3],
            ['code' => 'SCI101',  'name' => 'Science',            'type' => 'Core',     'credits' => 4],
            ['code' => 'PE101',   'name' => 'Physical Education', 'type' => 'Activity', 'credits' => 1],
        ];

        // Pick a few active branches that actually have a department to attach subjects to.
        $branchIds = DB::table('branches')
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->orderBy('id')
            ->limit(3)
            ->pluck('id');

        $totalSubjects = 0;
        $totalAssignments = 0;

        foreach ($branchIds as $branchId) {
            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id');

            $deptId = DB::table('departments')
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->value('id');

            if (!$deptId) {
                $this->command?->warn("Branch {$branchId} has no department — skipping.");
                continue;
            }

            // A grade value valid for this branch (falls back to any grade, then "1").
            $gradeValue = DB::table('grades')->where('branch_id', $branchId)->where('is_active', 1)->orderBy('value')->value('value')
                ?? DB::table('grades')->orderBy('value')->value('value')
                ?? '1';

            $subjectIds = [];
            foreach ($catalog as $item) {
                $subject = Subject::firstOrCreate(
                    ['branch_id' => $branchId, 'code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'description' => $item['name'] . ' (test data)',
                        'department_id' => $deptId,
                        'teacher_id' => null,
                        'grade_level' => $gradeValue,
                        'credits' => $item['credits'],
                        'type' => $item['type'],
                        'school_id' => $schoolId,
                        'is_active' => true,
                    ]
                );
                $subjectIds[] = $subject->id;
                $totalSubjects++;
            }

            // Assign the first 3 subjects to up to 2 sections of the matching grade.
            $sectionIds = DB::table('sections')
                ->where('branch_id', $branchId)
                ->where('grade_level', $gradeValue)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->limit(2)
                ->pluck('id');

            $teacherId = DB::table('users')
                ->where('branch_id', $branchId)
                ->where('role', 'Teacher')
                ->value('id');

            foreach ($sectionIds as $sectionId) {
                foreach (array_slice($subjectIds, 0, 3) as $subjectId) {
                    SectionSubject::firstOrCreate(
                        [
                            'section_id' => $sectionId,
                            'subject_id' => $subjectId,
                            'academic_year' => $ayName,
                        ],
                        [
                            'teacher_id' => $teacherId,
                            'branch_id' => $branchId,
                            'school_id' => $schoolId,
                            'academic_year_id' => $ayId,
                            'is_active' => true,
                        ]
                    );
                    $totalAssignments++;
                }
            }

            $this->command?->info("Branch {$branchId}: subjects + assignments seeded (grade {$gradeValue}).");
        }

        $this->command?->info("Done. ~{$totalSubjects} subjects, ~{$totalAssignments} assignments across " . $branchIds->count() . " branch(es).");
    }
}
