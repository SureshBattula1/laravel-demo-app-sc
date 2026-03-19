<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_leaves') && !Schema::hasColumn('student_leaves', 'academic_year_id')) {
            Schema::table('student_leaves', function (Blueprint $table) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('academic_years')
                    ->nullOnDelete();
                $table->index('academic_year_id');
            });
        }

        if (Schema::hasTable('teacher_leaves') && !Schema::hasColumn('teacher_leaves', 'academic_year_id')) {
            Schema::table('teacher_leaves', function (Blueprint $table) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('academic_years')
                    ->nullOnDelete();
                $table->index('academic_year_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_leaves') && Schema::hasColumn('student_leaves', 'academic_year_id')) {
            Schema::table('student_leaves', function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropIndex(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            });
        }

        if (Schema::hasTable('teacher_leaves') && Schema::hasColumn('teacher_leaves', 'academic_year_id')) {
            Schema::table('teacher_leaves', function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropIndex(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            });
        }
    }
};

