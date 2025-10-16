<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes
     */
    public function up(): void
    {
        // Users table indexes for search performance
        Schema::table('users', function (Blueprint $table) {
            // Check if indexes don't exist before creating
            try {
                $table->index('first_name', 'idx_users_first_name');
            } catch (\Exception $e) {}
            
            try {
                $table->index('last_name', 'idx_users_last_name');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['first_name', 'last_name'], 'idx_users_full_name');
            } catch (\Exception $e) {}
        });

        // Students table indexes for filtering and search
        Schema::table('students', function (Blueprint $table) {
            try {
                $table->index(['grade', 'section'], 'idx_students_grade_section');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['grade', 'section', 'student_status'], 'idx_students_grade_sec_status');
            } catch (\Exception $e) {}
            
            try {
                $table->index('student_status', 'idx_students_status');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['branch_id', 'grade', 'academic_year'], 'idx_students_branch_grade_year');
            } catch (\Exception $e) {}
        });

        // Branches table indexes for filtering
        Schema::table('branches', function (Blueprint $table) {
            try {
                $table->index(['city', 'state'], 'idx_branches_location');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['branch_type', 'status'], 'idx_branches_type_status');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['is_active', 'status'], 'idx_branches_active_status');
            } catch (\Exception $e) {}
            
            try {
                $table->index('parent_branch_id', 'idx_branches_parent');
            } catch (\Exception $e) {}
        });

        // Student Attendance indexes for better query performance
        Schema::table('student_attendance', function (Blueprint $table) {
            try {
                $table->index(['date', 'status'], 'idx_st_att_date_status');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['branch_id', 'date'], 'idx_st_att_branch_date');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['grade_level', 'section', 'date'], 'idx_st_att_grade_sec_date');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['academic_year', 'date'], 'idx_st_att_year_date');
            } catch (\Exception $e) {}
        });

        // Teacher Attendance indexes
        Schema::table('teacher_attendance', function (Blueprint $table) {
            try {
                $table->index(['date', 'status'], 'idx_tch_att_date_status');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['branch_id', 'date'], 'idx_tch_att_branch_date');
            } catch (\Exception $e) {}
        });

        // Teachers table indexes
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                try {
                    $table->index(['branch_id', 'teacher_status'], 'idx_teachers_branch_status');
                } catch (\Exception $e) {}
                
                try {
                    $table->index('teacher_status', 'idx_teachers_status');
                } catch (\Exception $e) {}
            });
        }

        // Fee structures indexes
        if (Schema::hasTable('fee_structures')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                try {
                    $table->index(['branch_id', 'academic_year'], 'idx_fee_str_branch_year');
                } catch (\Exception $e) {}
                
                try {
                    $table->index(['grade_level', 'academic_year'], 'idx_fee_str_grade_year');
                } catch (\Exception $e) {}
            });
        }

        // Fee payments indexes
        if (Schema::hasTable('fee_payments')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                try {
                    $table->index(['student_id', 'status'], 'idx_fee_pay_student_status');
                } catch (\Exception $e) {}
                
                try {
                    $table->index(['branch_id', 'status'], 'idx_fee_pay_branch_status');
                } catch (\Exception $e) {}
                
                try {
                    $table->index(['academic_year', 'status'], 'idx_fee_pay_year_status');
                } catch (\Exception $e) {}
            });
        }

        // Departments indexes
        Schema::table('departments', function (Blueprint $table) {
            try {
                $table->index(['branch_id', 'is_active'], 'idx_dept_branch_active');
            } catch (\Exception $e) {}
        });

        // Subjects indexes
        Schema::table('subjects', function (Blueprint $table) {
            try {
                $table->index(['branch_id', 'grade_level'], 'idx_subj_branch_grade');
            } catch (\Exception $e) {}
            
            try {
                $table->index(['department_id', 'is_active'], 'idx_subj_dept_active');
            } catch (\Exception $e) {}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes on users table
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_users_first_name');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_users_last_name');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_users_full_name');
            } catch (\Exception $e) {}
        });

        // Drop indexes on students table
        Schema::table('students', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_students_grade_section');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_students_grade_sec_status');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_students_status');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_students_branch_grade_year');
            } catch (\Exception $e) {}
        });

        // Drop indexes on branches table
        Schema::table('branches', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_branches_location');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_branches_type_status');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_branches_active_status');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_branches_parent');
            } catch (\Exception $e) {}
        });

        // Drop indexes on student_attendance table
        Schema::table('student_attendance', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_st_att_date_status');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_st_att_branch_date');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_st_att_grade_sec_date');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_st_att_year_date');
            } catch (\Exception $e) {}
        });

        // Drop indexes on teacher_attendance table
        Schema::table('teacher_attendance', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_tch_att_date_status');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_tch_att_branch_date');
            } catch (\Exception $e) {}
        });

        // Drop other indexes if tables exist
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_teachers_branch_status');
                } catch (\Exception $e) {}
                try {
                    $table->dropIndex('idx_teachers_status');
                } catch (\Exception $e) {}
            });
        }

        if (Schema::hasTable('fee_structures')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_fee_str_branch_year');
                } catch (\Exception $e) {}
                try {
                    $table->dropIndex('idx_fee_str_grade_year');
                } catch (\Exception $e) {}
            });
        }

        if (Schema::hasTable('fee_payments')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_fee_pay_student_status');
                } catch (\Exception $e) {}
                try {
                    $table->dropIndex('idx_fee_pay_branch_status');
                } catch (\Exception $e) {}
                try {
                    $table->dropIndex('idx_fee_pay_year_status');
                } catch (\Exception $e) {}
            });
        }

        Schema::table('departments', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_dept_branch_active');
            } catch (\Exception $e) {}
        });

        Schema::table('subjects', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_subj_branch_grade');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('idx_subj_dept_active');
            } catch (\Exception $e) {}
        });
    }
};
