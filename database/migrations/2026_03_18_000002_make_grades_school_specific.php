<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old global unique constraint on value FIRST, otherwise backfilling into
        // multiple schools will violate it (e.g. Grade value "1" repeats per school).
        try {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropUnique('grades_value_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropUnique(['value']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('grades', function (Blueprint $table) {
            if (!Schema::hasColumn('grades', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
                $table->index(['school_id', 'is_active', 'order'], 'idx_grades_school_active_order');
                $table->index(['school_id', 'value'], 'idx_grades_school_value');
            }
        });

        // Existing grades were global. Treat them as "template" (school_id = NULL).
        // Create school-specific copies for each school so each tenant can diverge safely.
        $schools = DB::table('schools')->select('id')->whereNull('deleted_at')->get();
        $templateGrades = DB::table('grades')->whereNull('school_id')->get();

        foreach ($schools as $school) {
            $exists = DB::table('grades')->where('school_id', $school->id)->exists();
            if ($exists) {
                continue;
            }
            foreach ($templateGrades as $g) {
                DB::table('grades')->insertOrIgnore([
                    'school_id' => $school->id,
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

        // If this migration was partially run before, duplicates may exist for (school_id, value).
        // Clean them up (keep the smallest id) so the unique index can be created safely.
        $duplicates = DB::table('grades')
            ->select('school_id', 'value', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->whereNotNull('school_id')
            ->groupBy('school_id', 'value')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $d) {
            DB::table('grades')
                ->where('school_id', $d->school_id)
                ->where('value', $d->value)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        // Add unique within school. Allows NULL school_id templates to coexist.
        Schema::table('grades', function (Blueprint $table) {
            $table->unique(['school_id', 'value'], 'uniq_grades_school_value');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            try {
                $table->dropUnique('uniq_grades_school_value');
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('grades', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropIndex('idx_grades_school_active_order');
                $table->dropIndex('idx_grades_school_value');
                $table->dropColumn('school_id');
            }
        });
    }
};

