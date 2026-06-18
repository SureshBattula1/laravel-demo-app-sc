<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add academic_year_id foreign key to key tables for academic year scoping.
     * Existing academic_year string columns are kept for backward compatibility; backfill separately.
     */
    public function up(): void
    {
        $tables = [
            'admission_applications',
            'students',
            'student_fees',
            'fee_payments',
            'fee_dues',
            'exam_terms',
            'exams',
            'holidays',
            'section_subjects',
            'classes',
            'student_groups',
            'student_imports',
            'student_attendance',
            'timetables',
            'invoices',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('academic_year_id')->nullable()->after('id')->constrained('academic_years')->onDelete('set null');
                $table->index('academic_year_id');
            });
        }

        // fee_structures uses uuid primary - add after existing academic_year column
        if (Schema::hasTable('fee_structures')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                $table->foreignId('academic_year_id')->nullable()->after('academic_year')->constrained('academic_years')->onDelete('set null');
                $table->index('academic_year_id');
            });
        }

        // class_upgrades: from/to academic year ids
        if (Schema::hasTable('class_upgrades')) {
            Schema::table('class_upgrades', function (Blueprint $table) {
                $table->foreignId('from_academic_year_id')->nullable()->after('academic_year_from')->constrained('academic_years')->onDelete('set null');
                $table->foreignId('to_academic_year_id')->nullable()->after('academic_year_to')->constrained('academic_years')->onDelete('set null');
                $table->index('from_academic_year_id');
                $table->index('to_academic_year_id');
            });
        }

        // teacher_attendance: add academic_year_id for year-scoped filtering
        if (Schema::hasTable('teacher_attendance')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                $table->foreignId('academic_year_id')->nullable()->after('branch_id')->constrained('academic_years')->onDelete('set null');
                $table->index('academic_year_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'admission_applications',
            'students',
            'student_fees',
            'fee_payments',
            'fee_dues',
            'exam_terms',
            'exams',
            'holidays',
            'section_subjects',
            'classes',
            'student_groups',
            'student_imports',
            'student_attendance',
            'timetables',
            'invoices',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropIndex(['academic_year_id']);
            });
        }

        if (Schema::hasTable('fee_structures')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropIndex(['academic_year_id']);
            });
        }

        if (Schema::hasTable('class_upgrades')) {
            Schema::table('class_upgrades', function (Blueprint $table) {
                $table->dropForeign(['from_academic_year_id']);
                $table->dropForeign(['to_academic_year_id']);
                $table->dropIndex(['from_academic_year_id']);
                $table->dropIndex(['to_academic_year_id']);
            });
        }

        if (Schema::hasTable('teacher_attendance')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                $table->dropForeign(['academic_year_id']);
                $table->dropIndex(['academic_year_id']);
            });
        }
    }
};
