<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Backfills the `departments` table (which was empty) with a standard set of
 * departments per branch. Idempotent: skips departments that already exist
 * (matched by branch_id + name).
 */
class DepartmentsBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // name => head (head must be letters/spaces only to satisfy the API's validator)
        $departments = [
            'Science'        => 'Head of Science',
            'Mathematics'    => 'Head of Mathematics',
            'English'        => 'Head of English',
            'Social Studies' => 'Head of Social Studies',
            'Languages'      => 'Head of Languages',
        ];

        $branches = DB::table('branches')->whereNull('deleted_at')->get();
        $created  = 0;

        foreach ($branches as $branch) {
            foreach ($departments as $name => $head) {
                $exists = DB::table('departments')
                    ->where('branch_id', $branch->id)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('departments')->insert([
                    'branch_id'        => $branch->id,
                    'school_id'        => $branch->school_id,
                    'name'             => $name,
                    'head'             => $head,
                    'description'      => $name . ' department',
                    'established_date' => '2015-06-01',
                    'students_count'   => 0,
                    'teachers_count'   => 0,
                    'is_active'        => 1,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                $created++;
            }
        }

        $this->command->info("✅ Departments backfill complete. Departments created: {$created} across {$branches->count()} branches.");
    }
}
