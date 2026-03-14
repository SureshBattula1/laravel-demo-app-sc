<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Replace global unique on sections.code with school-wise unique (school_id, code).
     * Section code (e.g. SEC-A) can be reused across different schools.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sections')) {
            return;
        }

        // Backfill school_id from branch_id for existing rows
        if (Schema::hasColumn('sections', 'school_id') && Schema::hasColumn('sections', 'branch_id')) {
            DB::statement('
                UPDATE sections s
                INNER JOIN branches b ON b.id = s.branch_id
                SET s.school_id = b.school_id
                WHERE s.branch_id IS NOT NULL AND (s.school_id IS NULL OR s.school_id = 0)
            ');
        }

        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['school_id', 'code'], 'sections_school_id_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sections')) {
            return;
        }

        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('sections_school_id_code_unique');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique('code');
        });
    }
};
