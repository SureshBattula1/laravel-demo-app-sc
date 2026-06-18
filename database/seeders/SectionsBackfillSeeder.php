<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Backfills the `sections` table (which was empty) by creating sections A & B
 * for every grade that has a branch. Idempotent: skips sections that already exist.
 */
class SectionsBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $now      = Carbon::now();
        $sections = ['A', 'B'];
        $created  = 0;

        $grades = DB::table('grades')
            ->whereNotNull('branch_id')
            ->orderBy('branch_id')
            ->orderBy('order')
            ->get();

        foreach ($grades as $grade) {
            $schoolId = $grade->school_id
                ?? DB::table('branches')->where('id', $grade->branch_id)->value('school_id');

            foreach ($sections as $name) {
                $exists = DB::table('sections')
                    ->where('branch_id', $grade->branch_id)
                    ->where('grade_level', (string) $grade->value)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('sections')->insert([
                    'branch_id'        => $grade->branch_id,
                    'school_id'        => $schoolId,
                    'name'             => $name,
                    'code'             => $grade->branch_id . '-' . $grade->value . '-' . $name,
                    'grade_level'      => (string) $grade->value,
                    'capacity'         => 40,
                    'current_strength' => 0,
                    'is_active'        => 1,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                $created++;
            }
        }

        $this->command->info("✅ Sections backfill complete. Sections created: {$created}");
    }
}
