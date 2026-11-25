<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Timetable;
use Database\Seeders\Concerns\ResolvesDemoSchoolContext;
use Illuminate\Database\Seeder;

/**
 * Class period rows for Green Valley Demo School (grades 1–2, sections A–C, Mon–Fri).
 */
class DemoTimetableSeeder extends Seeder
{
    use ResolvesDemoSchoolContext;

    private const DAYS = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
    ];

    /** period_number => [start, end, subject code suffix key] */
    private const PERIOD_TEMPLATE = [
        1 => ['09:00', '09:45', 'GV-MATH'],
        2 => ['09:50', '10:35', 'GV-ENG'],
        3 => ['10:50', '11:35', 'GV-SCI'],
        4 => ['11:40', '12:25', 'GV-HIN'],
        5 => ['13:30', '14:15', 'GV-PE'],
    ];

    public function run(): void
    {
        $ctx = $this->resolveDemoSchoolContext();
        if (! $ctx) {
            return;
        }

        $adminId = $ctx->branchAdmin?->id ?? $ctx->superAdmin?->id;
        $created = 0;

        foreach (['1', '2'] as $grade) {
            foreach (['A', 'B', 'C'] as $section) {
                foreach (self::DAYS as $day => $dayOrder) {
                    foreach (self::PERIOD_TEMPLATE as $periodNumber => [$start, $end, $codePrefix]) {
                        $subject = Subject::query()
                            ->where('branch_id', $ctx->branch->id)
                            ->where('code', $codePrefix.'-G'.$grade)
                            ->first();
                        if (! $subject || ! $subject->teacher_id) {
                            continue;
                        }

                        Timetable::firstOrCreate(
                            [
                                'branch_id' => $ctx->branch->id,
                                'grade' => $grade,
                                'section' => $section,
                                'day' => $day,
                                'period_number' => $periodNumber,
                                'academic_year_id' => $ctx->ay->id,
                            ],
                            [
                                'school_id' => $ctx->school->id,
                                'grade_level' => $grade,
                                'subject_id' => $subject->id,
                                'teacher_id' => $subject->teacher_id,
                                'day_order' => $dayOrder,
                                'start_time' => $start,
                                'end_time' => $end,
                                'room_number' => 'R-'.$grade.$section,
                                'academic_year' => $ctx->ay->name,
                                'is_active' => true,
                                'created_by' => $adminId,
                            ]
                        );
                        $created++;
                    }
                }
            }
        }

        $this->command?->info("Demo timetables: {$created} period slots ensured.");
    }
}