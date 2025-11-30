<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds critical indexes for performance optimization
     * Expected improvement: 50-80% faster queries
     */
    public function up(): void
    {
        // STUDENTS TABLE - Critical indexes
        if (Schema::hasTable('students')) {
            if (!$this->indexExists('students', 'idx_students_user_branch')) {
                DB::statement('CREATE INDEX idx_students_user_branch ON students(user_id, branch_id)');
            }
            if (!$this->indexExists('students', 'idx_students_branch_grade_section')) {
                DB::statement('CREATE INDEX idx_students_branch_grade_section ON students(branch_id, grade, section, student_status)');
            }
            if (!$this->indexExists('students', 'idx_students_grade_section_active')) {
                DB::statement('CREATE INDEX idx_students_grade_section_active ON students(grade, section, student_status)');
            }
            if (!$this->indexExists('students', 'idx_students_status_year')) {
                DB::statement('CREATE INDEX idx_students_status_year ON students(student_status, academic_year)');
            }
        }

        // TEACHERS TABLE - Critical indexes
        if (Schema::hasTable('teachers')) {
            if (!$this->indexExists('teachers', 'idx_teachers_user_branch')) {
                DB::statement('CREATE INDEX idx_teachers_user_branch ON teachers(user_id, branch_id)');
            }
            if (!$this->indexExists('teachers', 'idx_teachers_branch_dept')) {
                DB::statement('CREATE INDEX idx_teachers_branch_dept ON teachers(branch_id, department_id, teacher_status)');
            }
            if (!$this->indexExists('teachers', 'idx_teachers_status_active')) {
                DB::statement('CREATE INDEX idx_teachers_status_active ON teachers(teacher_status)');
            }
        }

        // USERS TABLE - Critical indexes
        if (Schema::hasTable('users')) {
            if (!$this->indexExists('users', 'idx_users_role_branch_active')) {
                DB::statement('CREATE INDEX idx_users_role_branch_active ON users(role, branch_id, is_active)');
            }
            if (!$this->indexExists('users', 'idx_users_email_active')) {
                DB::statement('CREATE INDEX idx_users_email_active ON users(email, is_active)');
            }
        }

        // STUDENT_ATTENDANCE TABLE - Critical indexes
        if (Schema::hasTable('student_attendance')) {
            if (!$this->indexExists('student_attendance', 'idx_student_att_branch_date_status')) {
                DB::statement('CREATE INDEX idx_student_att_branch_date_status ON student_attendance(branch_id, date, status)');
            }
            if (!$this->indexExists('student_attendance', 'idx_student_att_student_date')) {
                DB::statement('CREATE INDEX idx_student_att_student_date ON student_attendance(student_id, date)');
            }
            if (!$this->indexExists('student_attendance', 'idx_student_att_grade_section_date')) {
                DB::statement('CREATE INDEX idx_student_att_grade_section_date ON student_attendance(grade_level, section, date)');
            }
        }

        // TEACHER_ATTENDANCE TABLE - Critical indexes
        if (Schema::hasTable('teacher_attendance')) {
            if (!$this->indexExists('teacher_attendance', 'idx_teacher_att_branch_date_status')) {
                DB::statement('CREATE INDEX idx_teacher_att_branch_date_status ON teacher_attendance(branch_id, date, status)');
            }
            if (!$this->indexExists('teacher_attendance', 'idx_teacher_att_teacher_date')) {
                DB::statement('CREATE INDEX idx_teacher_att_teacher_date ON teacher_attendance(teacher_id, date)');
            }
        }

        // FEE_PAYMENTS TABLE - Critical indexes
        if (Schema::hasTable('fee_payments')) {
            if (!$this->indexExists('fee_payments', 'idx_fee_payments_student_date')) {
                DB::statement('CREATE INDEX idx_fee_payments_student_date ON fee_payments(student_id, payment_date)');
            }
            if (!$this->indexExists('fee_payments', 'idx_fee_payments_branch_status')) {
                DB::statement('CREATE INDEX idx_fee_payments_branch_status ON fee_payments(branch_id, payment_status)');
            }
            if (!$this->indexExists('fee_payments', 'idx_fee_payments_structure_status')) {
                DB::statement('CREATE INDEX idx_fee_payments_structure_status ON fee_payments(fee_structure_id, payment_status)');
            }
        }

        // BRANCHES TABLE - Critical indexes
        if (Schema::hasTable('branches')) {
            if (!$this->indexExists('branches', 'idx_branches_parent_active')) {
                DB::statement('CREATE INDEX idx_branches_parent_active ON branches(parent_branch_id, is_active)');
            }
            if (!$this->indexExists('branches', 'idx_branches_status_type')) {
                DB::statement('CREATE INDEX idx_branches_status_type ON branches(status, branch_type)');
            }
        }

        // GRADES TABLE - Critical indexes
        if (Schema::hasTable('grades')) {
            if (!$this->indexExists('grades', 'idx_grades_value_active')) {
                DB::statement('CREATE INDEX idx_grades_value_active ON grades(value, is_active)');
            }
            if (!$this->indexExists('grades', 'idx_grades_order_active')) {
                DB::statement('CREATE INDEX idx_grades_order_active ON grades(`order`, is_active)');
            }
        }

        // SECTIONS TABLE - Critical indexes
        if (Schema::hasTable('sections')) {
            if (!$this->indexExists('sections', 'idx_sections_branch_grade_active')) {
                DB::statement('CREATE INDEX idx_sections_branch_grade_active ON sections(branch_id, grade_level, is_active)');
            }
            if (!$this->indexExists('sections', 'idx_sections_grade_name')) {
                DB::statement('CREATE INDEX idx_sections_grade_name ON sections(grade_level, name)');
            }
        }

        // USER_ROLES TABLE - Critical for permission checks
        if (Schema::hasTable('user_roles')) {
            if (!$this->indexExists('user_roles', 'idx_user_roles_user_branch')) {
                DB::statement('CREATE INDEX idx_user_roles_user_branch ON user_roles(user_id, branch_id)');
            }
        }

        // ROLE_PERMISSIONS TABLE - Critical for permission checks
        if (Schema::hasTable('role_permissions')) {
            if (!$this->indexExists('role_permissions', 'idx_role_perms_role_permission')) {
                DB::statement('CREATE INDEX idx_role_perms_role_permission ON role_permissions(role_id, permission_id)');
            }
        }

        // PERMISSIONS TABLE - Critical for permission checks
        if (Schema::hasTable('permissions')) {
            if (!$this->indexExists('permissions', 'idx_permissions_slug')) {
                DB::statement('CREATE INDEX idx_permissions_slug ON permissions(slug)');
            }
        }

        // Update table statistics for better query optimization
        $tables = ['students', 'teachers', 'users', 'student_attendance', 'teacher_attendance', 
                   'fee_payments', 'branches', 'grades', 'sections', 'user_roles', 'role_permissions', 'permissions'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                try {
                    DB::statement("ANALYZE TABLE `{$table}`");
                } catch (\Exception $e) {
                    // Ignore errors for tables that don't support ANALYZE
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'students' => ['idx_students_user_branch', 'idx_students_branch_grade_section', 'idx_students_grade_section_active', 'idx_students_status_year'],
            'teachers' => ['idx_teachers_user_branch', 'idx_teachers_branch_dept', 'idx_teachers_status_active'],
            'users' => ['idx_users_role_branch_active', 'idx_users_email_active'],
            'student_attendance' => ['idx_student_att_branch_date_status', 'idx_student_att_student_date', 'idx_student_att_grade_section_date'],
            'teacher_attendance' => ['idx_teacher_att_branch_date_status', 'idx_teacher_att_teacher_date'],
            'fee_payments' => ['idx_fee_payments_student_date', 'idx_fee_payments_branch_status', 'idx_fee_payments_structure_status'],
            'branches' => ['idx_branches_parent_active', 'idx_branches_status_type'],
            'grades' => ['idx_grades_value_active', 'idx_grades_order_active'],
            'sections' => ['idx_sections_branch_grade_active', 'idx_sections_grade_name'],
            'user_roles' => ['idx_user_roles_user_branch'],
            'role_permissions' => ['idx_role_perms_role_permission'],
            'permissions' => ['idx_permissions_slug'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                foreach ($tableIndexes as $index) {
                    if ($this->indexExists($table, $index)) {
                        DB::statement("DROP INDEX `{$index}` ON `{$table}`");
                    }
                }
            }
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = DATABASE() 
             AND table_name = ? 
             AND index_name = ?",
            [$table, $index]
        );
        
        return $result[0]->count > 0;
    }
};

