<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add additional performance indexes
     * Note: Most indexes are already included in the main table migrations
     */
    public function up(): void
    {
        // Additional indexes for STUDENTS table
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                // Academic year index if not exists
                if (!$this->indexExists('students', 'idx_students_academic_year')) {
                    $table->index('academic_year', 'idx_students_academic_year');
                }
                
                // Gender index for demographic reports
                if (!$this->indexExists('students', 'idx_students_gender')) {
                    $table->index('gender', 'idx_students_gender');
                }
                
                // Admission date for date range queries
                if (!$this->indexExists('students', 'idx_students_admission_date')) {
                    $table->index('admission_date', 'idx_students_admission_date');
                }
                
                // Composite grade + section + status
                if (!$this->indexExists('students', 'idx_students_grade_sec_status')) {
                    $table->index(['grade', 'section', 'student_status'], 'idx_students_grade_sec_status');
                }
            });
        }

        // Additional indexes for USERS table
        Schema::table('users', function (Blueprint $table) {
            // Name search indexes
            if (!$this->indexExists('users', 'idx_users_first_name')) {
                $table->index('first_name', 'idx_users_first_name');
            }
            if (!$this->indexExists('users', 'idx_users_last_name')) {
                $table->index('last_name', 'idx_users_last_name');
            }
            if (!$this->indexExists('users', 'idx_users_full_name')) {
                $table->index(['first_name', 'last_name'], 'idx_users_full_name');
            }
            
            // User type + is_active composite
            if (!$this->indexExists('users', 'idx_users_type_active')) {
                $table->index(['user_type', 'is_active'], 'idx_users_type_active');
            }
        });

        // Full-text search indexes for better search performance
        try {
            DB::statement('ALTER TABLE users ADD FULLTEXT INDEX ft_users_search (first_name, last_name, email)');
        } catch (\Exception $e) {
            // Index might already exist or not supported
        }

        try {
            DB::statement('ALTER TABLE students ADD FULLTEXT INDEX ft_students_search (admission_number, roll_number)');
        } catch (\Exception $e) {
            // Index might already exist or not supported
        }

        // Additional indexes for FEE_PAYMENTS table
        if (Schema::hasTable('fee_payments')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                if (!$this->indexExists('fee_payments', 'idx_fee_payments_payment_date')) {
                    $table->index('payment_date', 'idx_fee_payments_payment_date');
                }
            });
        }

        // Additional indexes for EXAM_MARKS table
        if (Schema::hasTable('exam_marks')) {
            Schema::table('exam_marks', function (Blueprint $table) {
                if (!$this->indexExists('exam_marks', 'idx_exam_marks_is_pass')) {
                    $table->index('is_pass', 'idx_exam_marks_is_pass');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop students indexes
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                $this->dropIndexIfExists('students', 'idx_students_academic_year');
                $this->dropIndexIfExists('students', 'idx_students_gender');
                $this->dropIndexIfExists('students', 'idx_students_admission_date');
                $this->dropIndexIfExists('students', 'idx_students_grade_sec_status');
            });

            try {
                DB::statement('ALTER TABLE students DROP INDEX ft_students_search');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }

        // Drop users indexes
        Schema::table('users', function (Blueprint $table) {
            $this->dropIndexIfExists('users', 'idx_users_first_name');
            $this->dropIndexIfExists('users', 'idx_users_last_name');
            $this->dropIndexIfExists('users', 'idx_users_full_name');
            $this->dropIndexIfExists('users', 'idx_users_type_active');
        });

        try {
            DB::statement('ALTER TABLE users DROP INDEX ft_users_search');
        } catch (\Exception $e) {
            // Index might not exist
        }

        // Drop other indexes
        if (Schema::hasTable('fee_payments')) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $this->dropIndexIfExists('fee_payments', 'idx_fee_payments_payment_date');
            });
        }

        if (Schema::hasTable('exam_marks')) {
            Schema::table('exam_marks', function (Blueprint $table) {
                $this->dropIndexIfExists('exam_marks', 'idx_exam_marks_is_pass');
            });
        }
    }

    /**
     * Check if an index exists on a table
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

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
};

