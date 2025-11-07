<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for attendance modules
     */
    public function up(): void
    {
        // ============== STUDENT_ATTENDANCE TABLE INDEXES ==============
        if (Schema::hasTable('student_attendance')) {
            Schema::table('student_attendance', function (Blueprint $table) {
                // Index for status alone (very common filter)
                if (!$this->indexExists('student_attendance', 'idx_st_att_status')) {
                    $table->index('status', 'idx_st_att_status');
                }
                
                // Composite index for student_id + status (common query pattern)
                if (!$this->indexExists('student_attendance', 'idx_st_att_student_status')) {
                    $table->index(['student_id', 'status'], 'idx_st_att_student_status');
                }
                
                // Composite index for student_id + date (for individual student attendance)
                if (!$this->indexExists('student_attendance', 'idx_st_att_student_date')) {
                    $table->index(['student_id', 'date'], 'idx_st_att_student_date');
                }
                
                // Composite index for branch + status (for reports)
                if (!$this->indexExists('student_attendance', 'idx_st_att_branch_status')) {
                    $table->index(['branch_id', 'status'], 'idx_st_att_branch_status');
                }
                
                // Index for marked_by (audit queries)
                if (!$this->indexExists('student_attendance', 'idx_st_att_marked_by')) {
                    $table->index('marked_by', 'idx_st_att_marked_by');
                }
                
                // Composite index for grade_level + section + status (class-wise reports)
                if (!$this->indexExists('student_attendance', 'idx_st_att_grade_sec_status')) {
                    $table->index(['grade_level', 'section', 'status'], 'idx_st_att_grade_sec_status');
                }
                
                // Composite index for date + status (daily reports)
                if (!$this->indexExists('student_attendance', 'idx_st_att_date_status_v2')) {
                    $table->index(['date', 'status'], 'idx_st_att_date_status_v2');
                }
                
                // Composite index for branch + date + status (daily branch reports)
                if (!$this->indexExists('student_attendance', 'idx_st_att_branch_date_status')) {
                    $table->index(['branch_id', 'date', 'status'], 'idx_st_att_branch_date_status');
                }
            });
        }

        // ============== TEACHER_ATTENDANCE TABLE INDEXES ==============
        if (Schema::hasTable('teacher_attendance')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                // Index for status alone (very common filter)
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_status')) {
                    $table->index('status', 'idx_tch_att_status');
                }
                
                // Composite index for teacher_id + status
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_teacher_status')) {
                    $table->index(['teacher_id', 'status'], 'idx_tch_att_teacher_status');
                }
                
                // Composite index for teacher_id + date (for individual teacher attendance)
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_teacher_date')) {
                    $table->index(['teacher_id', 'date'], 'idx_tch_att_teacher_date');
                }
                
                // Composite index for branch + status (for reports)
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_branch_status')) {
                    $table->index(['branch_id', 'status'], 'idx_tch_att_branch_status');
                }
                
                // Composite index for date + status (daily reports)
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_date_status_v2')) {
                    $table->index(['date', 'status'], 'idx_tch_att_date_status_v2');
                }
                
                // Composite index for branch + date + status (daily branch reports)
                if (!$this->indexExists('teacher_attendance', 'idx_tch_att_branch_date_status')) {
                    $table->index(['branch_id', 'date', 'status'], 'idx_tch_att_branch_date_status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop student_attendance indexes
        if (Schema::hasTable('student_attendance')) {
            Schema::table('student_attendance', function (Blueprint $table) {
                if ($this->indexExists('student_attendance', 'idx_st_att_status')) {
                    $table->dropIndex('idx_st_att_status');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_student_status')) {
                    $table->dropIndex('idx_st_att_student_status');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_student_date')) {
                    $table->dropIndex('idx_st_att_student_date');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_branch_status')) {
                    $table->dropIndex('idx_st_att_branch_status');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_marked_by')) {
                    $table->dropIndex('idx_st_att_marked_by');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_grade_sec_status')) {
                    $table->dropIndex('idx_st_att_grade_sec_status');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_date_status_v2')) {
                    $table->dropIndex('idx_st_att_date_status_v2');
                }
                if ($this->indexExists('student_attendance', 'idx_st_att_branch_date_status')) {
                    $table->dropIndex('idx_st_att_branch_date_status');
                }
            });
        }

        // Drop teacher_attendance indexes
        if (Schema::hasTable('teacher_attendance')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_status')) {
                    $table->dropIndex('idx_tch_att_status');
                }
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_teacher_status')) {
                    $table->dropIndex('idx_tch_att_teacher_status');
                }
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_teacher_date')) {
                    $table->dropIndex('idx_tch_att_teacher_date');
                }
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_branch_status')) {
                    $table->dropIndex('idx_tch_att_branch_status');
                }
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_date_status_v2')) {
                    $table->dropIndex('idx_tch_att_date_status_v2');
                }
                if ($this->indexExists('teacher_attendance', 'idx_tch_att_branch_date_status')) {
                    $table->dropIndex('idx_tch_att_branch_date_status');
                }
            });
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
