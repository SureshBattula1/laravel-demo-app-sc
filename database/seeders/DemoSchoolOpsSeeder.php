<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

/**
 * Next step after DemoOneSchoolSeeder.
 *
 * Seeds subjects, exams, leaves, fee management, and holidays for
 * Green Valley Demo School using the school/branch/student/teacher IDs
 * created by DemoOneSchoolSeeder.
 *
 * php artisan db:seed --class=DemoSchoolOpsSeeder
 */
class DemoSchoolOpsSeeder extends Seeder
{
    public function run(): void
    {
        if (! School::query()->where('code', 'GV-DEMO')->exists()) {
            $this->command?->info('Demo school missing — running DemoOneSchoolSeeder first.');
            $this->call(DemoOneSchoolSeeder::class);
        }

        $this->call([
            DemoSchoolSubjectsSeeder::class,
            DemoSchoolExamsSeeder::class,
            DemoSchoolLeavesSeeder::class,
            DemoSchoolFeesSeeder::class,
            DemoSchoolHolidaysSeeder::class,
        ]);

        $this->command?->info('Demo school operations seeded (subjects, exams, leaves, fees, holidays).');
    }
}
