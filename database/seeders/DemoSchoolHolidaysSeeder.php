<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Database\Seeders\Concerns\ResolvesDemoSchoolContext;
use Illuminate\Database\Seeder;

/**
 * Branch-scoped holidays for Green Valley Demo School (current Indian FY).
 *
 * php artisan db:seed --class=DemoSchoolHolidaysSeeder
 */
class DemoSchoolHolidaysSeeder extends Seeder
{
    use ResolvesDemoSchoolContext;

    public function run(): void
    {
        $ctx = $this->resolveDemoSchoolContext();
        if (! $ctx) {
            return;
        }

        $startYear = (int) substr((string) $ctx->ay->name, 0, 4);
        $endYear = $startYear + 1;
        $createdBy = $ctx->branchAdmin?->id ?? $ctx->superAdmin?->id;

        $holidays = [
            ['title' => 'Independence Day', 'start' => $startYear.'-08-15', 'end' => $startYear.'-08-15', 'type' => 'National', 'color' => '#2e7d32'],
            ['title' => 'Teachers Day', 'start' => $startYear.'-09-05', 'end' => $startYear.'-09-05', 'type' => 'School', 'color' => '#1565c0'],
            ['title' => 'Gandhi Jayanti', 'start' => $startYear.'-10-02', 'end' => $startYear.'-10-02', 'type' => 'National', 'color' => '#2e7d32'],
            ['title' => 'Diwali Break', 'start' => $startYear.'-10-20', 'end' => $startYear.'-10-25', 'type' => 'School', 'color' => '#ef6c00'],
            ['title' => 'Annual Day', 'start' => $startYear.'-11-15', 'end' => $startYear.'-11-15', 'type' => 'School', 'color' => '#6a1b9a'],
            ['title' => 'Christmas', 'start' => $startYear.'-12-25', 'end' => $startYear.'-12-25', 'type' => 'National', 'color' => '#c62828'],
            ['title' => 'Republic Day', 'start' => $endYear.'-01-26', 'end' => $endYear.'-01-26', 'type' => 'National', 'color' => '#2e7d32'],
            ['title' => 'Holi', 'start' => $endYear.'-03-03', 'end' => $endYear.'-03-04', 'type' => 'School', 'color' => '#ef6c00'],
        ];

        foreach ($holidays as $row) {
            Holiday::firstOrCreate(
                [
                    'branch_id' => $ctx->branch->id,
                    'title' => $row['title'],
                    'start_date' => $row['start'],
                ],
                [
                    'school_id' => $ctx->school->id,
                    'name' => $row['title'],
                    'description' => $row['title'].' holiday for Green Valley Demo School',
                    'end_date' => $row['end'],
                    'date' => $row['start'],
                    'type' => $row['type'],
                    'color' => $row['color'],
                    'is_recurring' => $row['type'] === 'National',
                    'academic_year' => $ctx->ay->name,
                    'academic_year_id' => $ctx->ay->id,
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]
            );
        }

        $this->command?->info('Demo holidays: '.count($holidays).' holidays for '.$ctx->ay->name.'.');
    }
}
