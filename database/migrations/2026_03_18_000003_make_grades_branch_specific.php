<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            if (!Schema::hasColumn('grades', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('school_id')->constrained('branches')->nullOnDelete();
                $table->index(['branch_id', 'is_active', 'order'], 'idx_grades_branch_active_order');
                $table->index(['branch_id', 'value'], 'idx_grades_branch_value');
            }
        });

        // Drop any legacy unique constraints that prevent branch-specific rows.
        foreach (['grades_value_unique', 'uniq_grades_school_value'] as $idxName) {
            try {
                Schema::table('grades', function (Blueprint $table) use ($idxName) {
                    $table->dropUnique($idxName);
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
        try {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropUnique(['value']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        // Backfill branch grades for existing branches.
        // Strategy:
        // - Use school-scoped grades as the template for that branch's school.
        // - Insert missing (branch_id, value) rows only.
        $branches = DB::table('branches')
            ->select(['id', 'school_id'])
            ->whereNull('deleted_at')
            ->get();

        foreach ($branches as $branch) {
            if (empty($branch->school_id)) {
                continue;
            }

            $hasBranchGrades = DB::table('grades')->where('branch_id', $branch->id)->exists();
            if ($hasBranchGrades) {
                continue;
            }

            $template = DB::table('grades')
                ->where('school_id', $branch->school_id)
                ->whereNull('branch_id')
                ->get();

            if ($template->isEmpty()) {
                continue;
            }

            foreach ($template as $g) {
                DB::table('grades')->insertOrIgnore([
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'value' => $g->value,
                    'order' => $g->order,
                    'category' => $g->category,
                    'label' => $g->label,
                    'description' => $g->description,
                    'is_active' => $g->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Deduplicate (branch_id, value) if partial runs created duplicates.
        $duplicates = DB::table('grades')
            ->select('branch_id', 'value', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->whereNotNull('branch_id')
            ->groupBy('branch_id', 'value')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $d) {
            DB::table('grades')
                ->where('branch_id', $d->branch_id)
                ->where('value', $d->value)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        // Enforce uniqueness per branch (allows templates where branch_id is NULL).
        Schema::table('grades', function (Blueprint $table) {
            $table->unique(['branch_id', 'value'], 'uniq_grades_branch_value');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            try {
                $table->dropUnique('uniq_grades_branch_value');
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasColumn('grades', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropIndex('idx_grades_branch_active_order');
                $table->dropIndex('idx_grades_branch_value');
                $table->dropColumn('branch_id');
            }
        });
    }
};

