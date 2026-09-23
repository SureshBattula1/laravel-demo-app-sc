<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic years were global (shared across all companies) — a critical tenant leak
 * on /settings/academic-years. Scope them by company_id and remap existing FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            // Drop global unique so each company can have "2025-2026"
            try {
                $table->dropUnique(['name']);
            } catch (\Throwable $e) {
                // Index name may differ across environments
            }
        });

        Schema::table('academic_years', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_years', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            }
        });

        // Ensure unique per company (NULLs treated as distinct in MySQL for unique indexes)
        Schema::table('academic_years', function (Blueprint $table) {
            $table->unique(['company_id', 'name'], 'academic_years_company_name_unique');
        });

        $this->cloneYearsPerCompanyAndRemap();
    }

    protected function cloneYearsPerCompanyAndRemap(): void
    {
        $globals = DB::table('academic_years')
            ->whereNull('deleted_at')
            ->whereNull('company_id')
            ->orderBy('id')
            ->get();

        if ($globals->isEmpty()) {
            return;
        }

        $companyIds = DB::table('companies')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($companyIds)) {
            return;
        }

        // Map: companyId => [oldYearId => newYearId]
        $idMap = [];

        foreach ($companyIds as $companyId) {
            foreach ($globals as $year) {
                $existing = DB::table('academic_years')
                    ->where('company_id', $companyId)
                    ->where('name', $year->name)
                    ->whereNull('deleted_at')
                    ->value('id');

                if ($existing) {
                    $idMap[$companyId][(int) $year->id] = (int) $existing;
                    continue;
                }

                $newId = DB::table('academic_years')->insertGetId([
                    'company_id' => $companyId,
                    'name' => $year->name,
                    'start_date' => $year->start_date,
                    'end_date' => $year->end_date,
                    'is_current' => (bool) $year->is_current,
                    'is_active' => (bool) $year->is_active,
                    'description' => $year->description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $idMap[$companyId][(int) $year->id] = (int) $newId;
            }
        }

        $tablesWithBranch = [
            'students', 'student_enrollments', 'student_groups', 'student_imports',
            'student_leaves', 'student_attendance', 'student_fees',
            'assignments', 'classes', 'exam_terms', 'exams', 'fee_dues', 'fee_payments',
            'fee_structures', 'fee_types', 'holidays', 'invoices', 'section_subjects',
            'sms_bulk_queue', 'teacher_attendance', 'teacher_leaves', 'timetables',
            'admission_applications', 'book_issues', 'account_categories',
        ];

        foreach ($tablesWithBranch as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'academic_year_id')) {
                continue;
            }
            $hasBranch = Schema::hasColumn($table, 'branch_id');
            $hasSchool = Schema::hasColumn($table, 'school_id');
            if (!$hasBranch && !$hasSchool) {
                continue;
            }

            foreach ($companyIds as $companyId) {
                if (empty($idMap[$companyId])) {
                    continue;
                }
                foreach ($idMap[$companyId] as $oldId => $newId) {
                    if ($oldId === $newId) {
                        continue;
                    }
                    $q = DB::table($table)->where('academic_year_id', $oldId);
                    if ($hasBranch) {
                        $q->whereIn('branch_id', function ($sub) use ($companyId) {
                            $sub->select('branches.id')
                                ->from('branches')
                                ->join('schools', 'branches.school_id', '=', 'schools.id')
                                ->where('schools.company_id', $companyId)
                                ->whereNull('schools.deleted_at')
                                ->whereNull('branches.deleted_at');
                        });
                    } elseif ($hasSchool) {
                        $q->whereIn('school_id', function ($sub) use ($companyId) {
                            $sub->select('id')
                                ->from('schools')
                                ->where('company_id', $companyId)
                                ->whereNull('deleted_at');
                        });
                    }
                    $q->update(['academic_year_id' => $newId]);
                }
            }
        }

        // Soft-delete legacy global years so they no longer appear in company UIs
        DB::table('academic_years')
            ->whereNull('company_id')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            try {
                $table->dropUnique('academic_years_company_name_unique');
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('academic_years', 'company_id')) {
                $table->dropColumn('company_id');
            }
            $table->unique('name');
        });
    }
};
