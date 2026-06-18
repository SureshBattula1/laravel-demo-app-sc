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
            if (!$this->indexExists('users', 'idx_users_first_name')) {
                $table->index('first_name', 'idx_users_first_name');
            }
            
            if (!$this->indexExists('users', 'idx_users_last_name')) {
                $table->index('last_name', 'idx_users_last_name');
            }
            
            if (!$this->indexExists('users', 'idx_users_full_name')) {
                $table->index(['first_name', 'last_name'], 'idx_users_full_name');
            }
        });

        // Students table indexes for filtering and search
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (!$this->indexExists('students', 'idx_students_grade_section')) {
                    $table->index(['grade', 'section'], 'idx_students_grade_section');
                }
                
                if (!$this->indexExists('students', 'idx_students_grade_sec_status')) {
                    $table->index(['grade', 'section', 'student_status'], 'idx_students_grade_sec_status');
                }
                
                if (!$this->indexExists('students', 'idx_students_status')) {
                    $table->index('student_status', 'idx_students_status');
                }
                
                if (!$this->indexExists('students', 'idx_students_branch_grade_year')) {
                    $table->index(['branch_id', 'grade', 'academic_year'], 'idx_students_branch_grade_year');
                }
            });
        }

        // Branches table indexes for filtering
        Schema::table('branches', function (Blueprint $table) {
            if (!$this->indexExists('branches', 'idx_branches_location')) {
                $table->index(['city', 'state'], 'idx_branches_location');
            }
            
            if (!$this->indexExists('branches', 'idx_branches_active')) {
                $table->index('is_active', 'idx_branches_active');
            }
            
            if (!$this->indexExists('branches', 'idx_branches_main')) {
                $table->index('is_main_branch', 'idx_branches_main');
            }
        });

        // Student Attendance indexes for better query performance
        if (Schema::hasTable('student_attendance')) {
            Schema::table('student_attendance', function (Blueprint $table) {
                if (!$this->indexExists('student_attendance', 'idx_st_att_date_status')) {
                    $table->index(['date', 'status'], 'idx_st_att_date_status');
                }
                
                if (!$this->indexExists('student_attendance', 'idx_st_att_branch_date')) {
                    $table->index(['branch_id', 'date'], 'idx_st_att_branch_date');
                }
                
                if (!$this->indexExists('student_attendance', 'idx_st_att_grade_sec_date')) {
                    $table->index(['grade_level', 'section', 'date'], 'idx_st_att_grade_sec_date');
                }
                
                if (!$this->indexExists('student_attendance', 'idx_st_att_year_date')) {
                    $table->index(['academic_year', 'date'], 'idx_st_att_year_date');
                }
            });
        }

        // Teacher Attendance indexes
        if (Schema::hasTable('teacher_attendance')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_date_status')) {
                    $table->index(['date', 'status'], 'idx_tch_att_date_status');
                }
                
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_branch_date')) {
                    $table->index(['branch_id', 'date'], 'idx_tch_att_branch_date');
                }
            });
        }

        // Teachers table indexes
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                if (!$this->indexExists('teachers', 'idx_teachers_branch_status')) {
                    $table->index(['branch_id', 'teacher_status'], 'idx_teachers_branch_status');
                }
                
                if (!$this->indexExists('teachers', 'idx_teachers_status')) {
                    $table->index('teacher_status', 'idx_teachers_status');
                }
            });
        }

        // Fee structures indexes
        if (Schema::hasTable('fee_structures')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                if (!$this->indexExists('fee_structures', 'idx_fee_str_branch_year')) {
                    $table->index(['branch_id', 'academic_year'], 'idx_fee_str_branch_year');
                }
                
                if (!$this->indexExists('fee_structures', 'idx_fee_str_grade_year')) {
                    $table->index(['grade_level', 'academic_year'], 'idx_fee_str_grade_year');
                }
            });
        }

        // Fee payments indexes
        if (Schema::hasTable('fee_payments')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                if (!$this->indexExists('fee_payments', 'idx_fee_pay_student_status')) {
                    $table->index(['student_id', 'status'], 'idx_fee_pay_student_status');
                }
                
                if (!$this->indexExists('fee_payments', 'idx_fee_pay_branch_status')) {
                    $table->index(['branch_id', 'status'], 'idx_fee_pay_branch_status');
                }
                
                if (!$this->indexExists('fee_payments', 'idx_fee_pay_year_status')) {
                    $table->index(['academic_year', 'status'], 'idx_fee_pay_year_status');
                }
            });
        }

        // Departments indexes
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if (!$this->indexExists('departments', 'idx_dept_branch_active')) {
                    $table->index(['branch_id', 'is_active'], 'idx_dept_branch_active');
                }
            });
        }

        // Subjects indexes
        if (Schema::hasTable('subjects')) {
            Schema::table('subjects', function (Blueprint $table) {
                if (!$this->indexExists('subjects', 'idx_subj_branch_grade')) {
                    $table->index(['branch_id', 'grade_level'], 'idx_subj_branch_grade');
                }
                
                if (!$this->indexExists('subjects', 'idx_subj_dept_active')) {
                    $table->index(['department_id', 'is_active'], 'idx_subj_dept_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes on users table
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_first_name')) {
                $table->dropIndex('idx_users_first_name');
            }
            if ($this->indexExists('users', 'idx_users_last_name')) {
                $table->dropIndex('idx_users_last_name');
            }
            if ($this->indexExists('users', 'idx_users_full_name')) {
                $table->dropIndex('idx_users_full_name');
            }
        });

        // Drop indexes on students table
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if ($this->indexExists('students', 'idx_students_grade_section')) {
                    $table->dropIndex('idx_students_grade_section');
                }
                if ($this->indexExists('students', 'idx_students_grade_sec_status')) {
                    $table->dropIndex('idx_students_grade_sec_status');
                }
                if ($this->indexExists('students', 'idx_students_status')) {
                    $table->dropIndex('idx_students_status');
                }
                if ($this->indexExists('students', 'idx_students_branch_grade_year')) {
                    $table->dropIndex('idx_students_branch_grade_year');
                }
            });
        }

        // Drop indexes on branches table
        Schema::table('branches', function (Blueprint $table) {
            if ($this->indexExists('branches', 'idx_branches_location')) {
                $table->dropIndex('idx_branches_location');
            }
            if ($this->indexExists('branches', 'idx_branches_active')) {
                $table->dropIndex('idx_branches_active');
            }
            if ($this->indexExists('branches', 'idx_branches_main')) {
                $table->dropIndex('idx_branches_main');
            }
        });

        // Drop indexes on student_attendance table
        if (Schema::hasTable('student_attendance')) {
            Schema::table('student_attendance', function (Blueprint $table) {
                if ($this->indexExists('student_attendance', 'idx_st_att_date_status')) {
                    $table->dropIndex('idx_st_att_date_status');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_branch_date')) {
                    $table->dropIndex('idx_st_att_branch_date');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_grade_sec_date')) {
                    $table->dropIndex('idx_st_att_grade_sec_date');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_year_date')) {
                    $table->dropIndex('idx_st_att_year_date');
                }
            });
        }

        // Drop indexes on teacher_attendance table
        if (Schema::hasTable('teacher_attendance')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_date_status')) {
                    $table->dropIndex('idx_tch_att_date_status');
                }
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_branch_date')) {
                    $table->dropIndex('idx_tch_att_branch_date');
                }
            });
        }

        // Drop other indexes if tables exist
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                if ($this->indexExists('teachers', 'idx_teachers_branch_status')) {
                    $table->dropIndex('idx_teachers_branch_status');
                }
                if ($this->indexExists('teachers', 'idx_teachers_status')) {
                    $table->dropIndex('idx_teachers_status');
                }
            });
        }

        if (Schema::hasTable('fee_structures')) {
            Schema::table('fee_structures', function (Blueprint $table) {
                if ($this->indexExists('fee_structures', 'idx_fee_str_branch_year')) {
                    $table->dropIndex('idx_fee_str_branch_year');
                }
                if ($this->indexExists('fee_structures', 'idx_fee_str_grade_year')) {
                    $table->dropIndex('idx_fee_str_grade_year');
                }
            });
        }

        if (Schema::hasTable('fee_payments')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                if ($this->indexExists('fee_payments', 'idx_fee_pay_student_status')) {
                    $table->dropIndex('idx_fee_pay_student_status');
                }
                if ($this->indexExists('fee_payments', 'idx_fee_pay_branch_status')) {
                    $table->dropIndex('idx_fee_pay_branch_status');
                }
                if ($this->indexExists('fee_payments', 'idx_fee_pay_year_status')) {
                    $table->dropIndex('idx_fee_pay_year_status');
                }
            });
        }

        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if ($this->indexExists('departments', 'idx_dept_branch_active')) {
                    $table->dropIndex('idx_dept_branch_active');
                }
            });
        }

        if (Schema::hasTable('subjects')) {
            Schema::table('subjects', function (Blueprint $table) {
                if ($this->indexExists('subjects', 'idx_subj_branch_grade')) {
                    $table->dropIndex('idx_subj_branch_grade');
                }
                if ($this->indexExists('subjects', 'idx_subj_dept_active')) {
                    $table->dropIndex('idx_subj_dept_active');
                }
            });
        }
    }

    /**
     * Check if an index exists on a table.
     *
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT COUNT(*) as count 
             FROM information_schema.statistics 
             WHERE table_schema = ? 
             AND table_name = ? 
             AND index_name = ?",
            [$databaseName, $tableName, $indexName]
        );
        
        return $result[0]->count > 0;
    }
};
