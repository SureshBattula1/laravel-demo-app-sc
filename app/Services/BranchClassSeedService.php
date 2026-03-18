<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BranchClassSeedService
{
    /**
     * Ensure default "class" rows exist for a branch for the current academic year.
     *
     * This seeds one row per grade with section = NULL (sections are created manually).
     * Idempotent even when `section` is nullable (MySQL allows multiple NULLs in UNIQUE indexes).
     */
    public function ensureDefaultsForBranch(int $branchId): void
    {
        $branch = Branch::query()->select(['id', 'school_id'])->find($branchId);
        if (!$branch) {
            Log::warning('BranchClassSeedService: branch not found', ['branch_id' => $branchId]);
            return;
        }

        if (empty($branch->school_id)) {
            Log::warning('BranchClassSeedService: branch has no school_id, skipping', ['branch_id' => $branchId]);
            return;
        }

        $currentYearName = AcademicYear::query()->current()->active()->value('name');
        if (!$currentYearName) {
            Log::warning('BranchClassSeedService: no current academic year, skipping class seeding', [
                'branch_id' => $branchId,
                'school_id' => $branch->school_id,
            ]);
            return;
        }

        $grades = DB::table('grades')
            ->where('school_id', (int) $branch->school_id)
            ->where('is_active', true)
            ->orderByRaw('CAST(value AS UNSIGNED) asc')
            ->orderBy('value', 'asc')
            ->pluck('value')
            ->filter()
            ->values()
            ->toArray();

        if (empty($grades)) {
            Log::warning('BranchClassSeedService: no grades found for school, skipping', [
                'branch_id' => $branchId,
                'school_id' => $branch->school_id,
            ]);
            return;
        }

        // MySQL UNIQUE(branch_id, grade, section, academic_year) does NOT protect section=NULL duplicates.
        // So we explicitly compute which grade rows are missing.
        $existingGrades = DB::table('classes')
            ->where('branch_id', $branchId)
            ->whereNull('section')
            ->where('academic_year', $currentYearName)
            ->whereIn('grade', $grades)
            ->pluck('grade')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $missingGrades = array_values(array_diff($grades, $existingGrades));
        if (empty($missingGrades)) {
            return;
        }

        $now = now();
        $hasSchoolIdColumn = Schema::hasColumn('classes', 'school_id');

        $rows = [];
        foreach ($missingGrades as $grade) {
            $row = [
                'branch_id' => $branchId,
                'grade' => (string) $grade,
                'section' => null,
                'class_name' => 'Grade ' . (string) $grade,
                'academic_year' => (string) $currentYearName,
                'capacity' => 40,
                'current_strength' => 0,
                'room_number' => null,
                'description' => null,
                'class_teacher_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasSchoolIdColumn) {
                $row['school_id'] = (int) $branch->school_id;
            }

            $rows[] = $row;
        }

        DB::table('classes')->insert($rows);
    }
}

