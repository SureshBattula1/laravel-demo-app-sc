<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add missing performance indexes for student module
     */
    public function up(): void
    {
        // ============== STUDENTS TABLE INDEXES ==============
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                // Index for student_status filtering (very common query)
                if (!$this->indexExists('students', 'idx_students_status_v2')) {
                    $table->index('student_status', 'idx_students_status_v2');
                }
                
                // Index for academic_year filtering
                if (!$this->indexExists('students', 'idx_students_academic_year')) {
                    $table->index('academic_year', 'idx_students_academic_year');
                }
                
                // Composite index for grade + section (very common filter combination)
                if (!$this->indexExists('students', 'idx_students_grade_section_v2')) {
                    $table->index(['grade', 'section'], 'idx_students_grade_section_v2');
                }
                
                // Composite index for grade + section + status (common filter combo)
                if (!$this->indexExists('students', 'idx_students_grade_sec_status_v2')) {
                    $table->index(['grade', 'section', 'student_status'], 'idx_students_grade_sec_status_v2');
                }
                
                // Composite index for branch + grade + academic_year (reporting queries)
                if (!$this->indexExists('students', 'idx_students_branch_grade_year_v2')) {
                    $table->index(['branch_id', 'grade', 'academic_year'], 'idx_students_branch_grade_year_v2');
                }
                
                // Index for gender filtering
                if (!$this->indexExists('students', 'idx_students_gender')) {
                    $table->index('gender', 'idx_students_gender');
                }
                
                // Index for admission_date for date range queries
                if (!$this->indexExists('students', 'idx_students_admission_date')) {
                    $table->index('admission_date', 'idx_students_admission_date');
                }
            });
        }

        // ============== USERS TABLE INDEXES ==============
        Schema::table('users', function (Blueprint $table) {
            // Index for first_name searches
            if (!$this->indexExists('users', 'idx_users_first_name_v2')) {
                $table->index('first_name', 'idx_users_first_name_v2');
            }
            
            // Index for last_name searches
            if (!$this->indexExists('users', 'idx_users_last_name_v2')) {
                $table->index('last_name', 'idx_users_last_name_v2');
            }
            
            // Composite index for full name searches
            if (!$this->indexExists('users', 'idx_users_full_name_v2')) {
                $table->index(['first_name', 'last_name'], 'idx_users_full_name_v2');
            }
            
            // Index for email searches
            if (!$this->indexExists('users', 'idx_users_email_search')) {
                $table->index('email', 'idx_users_email_search');
            }
            
            // Index for phone searches
            if (!$this->indexExists('users', 'idx_users_phone')) {
                $table->index('phone', 'idx_users_phone');
            }
            
            // Index for user_type + is_active (common filter)
            if (!$this->indexExists('users', 'idx_users_type_active')) {
                $table->index(['user_type', 'is_active'], 'idx_users_type_active');
            }
        });

        // Add full-text search indexes for better search performance
        // Note: Full-text indexes work best for natural language searches
        try {
            // Full-text index on users for name and email search
            DB::statement('ALTER TABLE users ADD FULLTEXT INDEX ft_users_search (first_name, last_name, email)');
        } catch (\Exception $e) {
            // Index might already exist or MySQL version doesn't support it
            \Log::info('Fulltext index on users already exists or not supported: ' . $e->getMessage());
        }

        try {
            // Full-text index on students for admission/roll number search
            DB::statement('ALTER TABLE students ADD FULLTEXT INDEX ft_students_search (admission_number, roll_number)');
        } catch (\Exception $e) {
            \Log::info('Fulltext index on students already exists or not supported: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop students table indexes
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if ($this->indexExists('students', 'idx_students_status_v2')) {
                    $table->dropIndex('idx_students_status_v2');
                }
                if ($this->indexExists('students', 'idx_students_academic_year')) {
                    $table->dropIndex('idx_students_academic_year');
                }
                if ($this->indexExists('students', 'idx_students_grade_section_v2')) {
                    $table->dropIndex('idx_students_grade_section_v2');
                }
                if ($this->indexExists('students', 'idx_students_grade_sec_status_v2')) {
                    $table->dropIndex('idx_students_grade_sec_status_v2');
                }
                if ($this->indexExists('students', 'idx_students_branch_grade_year_v2')) {
                    $table->dropIndex('idx_students_branch_grade_year_v2');
                }
                if ($this->indexExists('students', 'idx_students_gender')) {
                    $table->dropIndex('idx_students_gender');
                }
                if ($this->indexExists('students', 'idx_students_admission_date')) {
                    $table->dropIndex('idx_students_admission_date');
                }
            });

            // Drop full-text index on students
            try {
                DB::statement('ALTER TABLE students DROP INDEX ft_students_search');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }

        // Drop users table indexes
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_first_name_v2')) {
                $table->dropIndex('idx_users_first_name_v2');
            }
            if ($this->indexExists('users', 'idx_users_last_name_v2')) {
                $table->dropIndex('idx_users_last_name_v2');
            }
            if ($this->indexExists('users', 'idx_users_full_name_v2')) {
                $table->dropIndex('idx_users_full_name_v2');
            }
            if ($this->indexExists('users', 'idx_users_email_search')) {
                $table->dropIndex('idx_users_email_search');
            }
            if ($this->indexExists('users', 'idx_users_phone')) {
                $table->dropIndex('idx_users_phone');
            }
            if ($this->indexExists('users', 'idx_users_type_active')) {
                $table->dropIndex('idx_users_type_active');
            }
        });

        // Drop full-text index on users
        try {
            DB::statement('ALTER TABLE users DROP INDEX ft_users_search');
        } catch (\Exception $e) {
            // Index might not exist
        }
    }

    /**
     * Check if an index exists on a table.
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
