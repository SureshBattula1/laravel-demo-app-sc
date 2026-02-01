<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations - Migrate existing data to multi-school architecture
     */
    public function up(): void
    {
        try {
            DB::beginTransaction();

            // Step 1: Create default company
            $defaultCompany = DB::table('companies')->insertGetId([
                'name' => 'Default Company',
                'code' => 'DEFAULT',
                'email' => 'admin@default.com',
                'phone' => '0000000000',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Created default company', ['company_id' => $defaultCompany]);

            // Step 2: Convert top-level branches to schools
            // Get all branches that don't have a parent (top-level branches)
            $topLevelBranches = DB::table('branches')
                ->whereNull('parent_branch_id')
                ->whereNull('deleted_at')
                ->get();

            foreach ($topLevelBranches as $branch) {
                // Create school for this branch
                $schoolId = DB::table('schools')->insertGetId([
                    'company_id' => $defaultCompany,
                    'name' => $branch->name . ' School',
                    'code' => $branch->code . '_SCHOOL',
                    'main_branch_id' => $branch->id,
                    'status' => $branch->is_active ? 'Active' : 'Inactive',
                    'created_at' => $branch->created_at ?? now(),
                    'updated_at' => $branch->updated_at ?? now()
                ]);

                // Update the branch to link to the school
                DB::table('branches')
                    ->where('id', $branch->id)
                    ->update(['school_id' => $schoolId]);

                // Update all child branches to link to the same school
                $this->updateChildBranches($branch->id, $schoolId);

                Log::info('Created school from branch', [
                    'branch_id' => $branch->id,
                    'school_id' => $schoolId
                ]);
            }

            // Step 3: Update all records with branch_id to also have school_id
            $this->updateRecordsWithSchoolId();

            DB::commit();
            Log::info('Data migration completed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Data migration failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Recursively update child branches with school_id
     */
    private function updateChildBranches(int $parentBranchId, int $schoolId): void
    {
        $childBranches = DB::table('branches')
            ->where('parent_branch_id', $parentBranchId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($childBranches as $childBranch) {
            DB::table('branches')
                ->where('id', $childBranch->id)
                ->update(['school_id' => $schoolId]);

            // Recursively update children
            $this->updateChildBranches($childBranch->id, $schoolId);
        }
    }

    /**
     * Update all records with branch_id to also have school_id
     */
    private function updateRecordsWithSchoolId(): void
    {
        // List of tables that have branch_id and need school_id
        $tables = [
            'departments',
            'sections',
            'classes',
            'subjects',
            'section_subjects',
            'student_groups',
            'student_leaves',
            'teacher_leaves',
            'student_attendance',
            'teacher_attendance',
            'fee_types',
            'fee_structures',
            'fee_payments',
            'transactions',
            'budgets',
            'invoices',
            'account_categories',
            'events',
            'holidays',
            'books',
            'book_issues',
            'transport_routes',
            'vehicles',
            'timetables',
            'exams',
            'exam_terms',
            'exam_schedules',
            'exam_results',
            'exam_marks',
            'exam_attendance',
            'admission_applications',
            'announcements',
            'circulars',
            'assignments',
            'branch_transfers',
            'branch_analytics',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id') && Schema::hasColumn($table, 'school_id')) {
                // Update school_id based on branch_id
                DB::statement("
                    UPDATE {$table} t
                    INNER JOIN branches b ON t.branch_id = b.id
                    SET t.school_id = b.school_id
                    WHERE t.branch_id IS NOT NULL
                    AND b.school_id IS NOT NULL
                    AND t.school_id IS NULL
                ");

                Log::info("Updated school_id for table: {$table}");
            }
        }

        // Update students and teachers tables
        if (Schema::hasTable('students') && Schema::hasColumn('students', 'branch_id') && Schema::hasColumn('students', 'school_id')) {
            DB::statement("
                UPDATE students s
                INNER JOIN branches b ON s.branch_id = b.id
                SET s.school_id = b.school_id
                WHERE s.branch_id IS NOT NULL
                AND b.school_id IS NOT NULL
                AND s.school_id IS NULL
            ");
        }

        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'branch_id') && Schema::hasColumn('teachers', 'school_id')) {
            DB::statement("
                UPDATE teachers t
                INNER JOIN branches b ON t.branch_id = b.id
                SET t.school_id = b.school_id
                WHERE t.branch_id IS NOT NULL
                AND b.school_id IS NOT NULL
                AND t.school_id IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration should not be reversed as it's a data migration
        // The schema changes are handled by other migrations
        Log::warning('Data migration rollback not supported');
    }
};

