<?php

namespace Database\Seeders;

use App\Models\SectionSubject;
use App\Models\Subject;
use Database\Seeders\Concerns\ResolvesDemoSchoolContext;
use Illuminate\Database\Seeder;

/**
 * Subjects + section assignments for Green Valley Demo School.
 *
 * php artisan db:seed --class=DemoSchoolSubjectsSeeder
 */
class DemoSchoolSubjectsSeeder extends Seeder
{
    use ResolvesDemoSchoolContext;

    public function run(): void
    {
        $ctx = $this->resolveDemoSchoolContext();
        if (! $ctx) {
            return;
        }

        $catalog = [
            ['code' => 'GV-MATH', 'name' => 'Mathematics', 'type' => 'Core', 'credits' => 4, 'dept' => 'Mathematics', 'teacher' => ['1' => 3, '2' => 7]],
            ['code' => 'GV-ENG', 'name' => 'English', 'type' => 'Language', 'credits' => 3, 'dept' => 'Languages', 'teacher' => ['1' => 4, '2' => 8]],
            ['code' => 'GV-HIN', 'name' => 'Hindi', 'type' => 'Language', 'credits' => 3, 'dept' => 'Languages', 'teacher' => ['1' => 5, '2' => 5]],
            ['code' => 'GV-SCI', 'name' => 'Science', 'type' => 'Core', 'credits' => 4, 'dept' => 'Science', 'teacher' => ['1' => 1, '2' => 2]],
            ['code' => 'GV-PE', 'name' => 'Physical Education', 'type' => 'Activity', 'credits' => 1, 'dept' => 'Science', 'teacher' => ['1' => 6, '2' => 9]],
        ];

        $subjects = 0;
        $assignments = 0;

        foreach (['1', '2'] as $grade) {
            foreach ($catalog as $item) {
                $teacherId = $this->demoTeacherUserId($item['teacher'][$grade]);
                $subject = Subject::firstOrCreate(
                    ['branch_id' => $ctx->branch->id, 'code' => $item['code'].'-G'.$grade],
                    [
                        'school_id' => $ctx->school->id,
                        'department_id' => $this->demoDepartmentId((int) $ctx->branch->id, $item['dept']),
                        'teacher_id' => $teacherId,
                        'name' => $item['name'],
                        'description' => $item['name'].' for Grade '.$grade,
                        'grade_level' => $grade,
                        'type' => $item['type'],
                        'credits' => $item['credits'],
                        'is_active' => true,
                    ]
                );
                $subjects++;

                foreach (['A', 'B', 'C'] as $section) {
                    $sectionId = $this->demoSectionId((int) $ctx->branch->id, $grade, $section);
                    if (! $sectionId) {
                        continue;
                    }

                    SectionSubject::firstOrCreate(
                        [
                            'section_id' => $sectionId,
                            'subject_id' => $subject->id,
                            'academic_year' => $ctx->ay->name,
                        ],
                        [
                            'branch_id' => $ctx->branch->id,
                            'school_id' => $ctx->school->id,
                            'academic_year_id' => $ctx->ay->id,
                            'teacher_id' => $teacherId,
                            'is_active' => true,
                        ]
                    );
                    $assignments++;
                }
            }
        }

        $this->command?->info("Demo subjects: {$subjects} subjects, {$assignments} section assignments.");
    }
}
