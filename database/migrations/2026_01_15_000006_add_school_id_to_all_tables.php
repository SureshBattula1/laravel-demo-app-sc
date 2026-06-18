<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add school_id to all tables with branch_id
     */
    public function up(): void
    {
        // Tables with branch_id that need school_id
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
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $tableSchema) use ($table) {
                    // Check if school_id doesn't already exist
                    if (!Schema::hasColumn($table, 'school_id')) {
                        $tableSchema->foreignId('school_id')->nullable()->after('branch_id')->constrained('schools')->onDelete('cascade');
                        $tableSchema->index('school_id');
                        
                        // Add composite index for common queries
                        if (Schema::hasColumn($table, 'is_active')) {
                            $tableSchema->index(['school_id', 'is_active']);
                        }
                    }
                });
            }
        }

        // Special handling for students and teachers tables (they reference users, not branches directly)
        // But they might have branch_id through relationships
        if (Schema::hasTable('students') && Schema::hasColumn('students', 'branch_id')) {
            Schema::table('students', function (Blueprint $table) {
                if (!Schema::hasColumn('students', 'school_id')) {
                    $table->foreignId('school_id')->nullable()->after('branch_id')->constrained('schools')->onDelete('cascade');
                    $table->index('school_id');
                }
            });
        }

        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'branch_id')) {
            Schema::table('teachers', function (Blueprint $table) {
                if (!Schema::hasColumn('teachers', 'school_id')) {
                    $table->foreignId('school_id')->nullable()->after('branch_id')->constrained('schools')->onDelete('cascade');
                    $table->index('school_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
            'students',
            'teachers',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'school_id')) {
                Schema::table($table, function (Blueprint $tableSchema) use ($table) {
                    $tableSchema->dropForeign([$table . '_school_id_foreign']);
                    if (Schema::hasColumn($table, 'is_active')) {
                        $tableSchema->dropIndex([$table . '_school_id_is_active_index']);
                    }
                    $tableSchema->dropIndex([$table . '_school_id_index']);
                    $tableSchema->dropColumn('school_id');
                });
            }
        }
    }
};

