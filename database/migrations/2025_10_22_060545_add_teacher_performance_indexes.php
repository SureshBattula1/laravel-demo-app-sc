<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for teacher module
     */
    public function up(): void
    {
        // ============== TEACHERS TABLE INDEXES ==============
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                // Index for employee_id (unique identifier, frequently searched)
                if (!$this->indexExists('teachers', 'idx_teachers_employee_id')) {
                    $table->index('employee_id', 'idx_teachers_employee_id');
                }
                
                // Composite index for branch + teacher_status (very common filter)
                if (!$this->indexExists('teachers', 'idx_teachers_branch_status_v2')) {
                    $table->index(['branch_id', 'teacher_status'], 'idx_teachers_branch_status_v2');
                }
                
                // Index for teacher_status alone (for filtering active/inactive teachers)
                if (!$this->indexExists('teachers', 'idx_teachers_status_v2')) {
                    $table->index('teacher_status', 'idx_teachers_status_v2');
                }
                
                // Index for department_id (for department-wise filtering)
                if (!$this->indexExists('teachers', 'idx_teachers_department')) {
                    $table->index('department_id', 'idx_teachers_department');
                }
                
                // Composite index for branch + department
                if (!$this->indexExists('teachers', 'idx_teachers_branch_dept')) {
                    $table->index(['branch_id', 'department_id'], 'idx_teachers_branch_dept');
                }
                
                // Index for category_type (Teaching/Non-Teaching filter)
                if (!$this->indexExists('teachers', 'idx_teachers_category')) {
                    $table->index('category_type', 'idx_teachers_category');
                }
                
                // Index for designation (for role-based filtering)
                if (!$this->indexExists('teachers', 'idx_teachers_designation')) {
                    $table->index('designation', 'idx_teachers_designation');
                }
                
                // Index for gender (for filtering)
                if (!$this->indexExists('teachers', 'idx_teachers_gender')) {
                    $table->index('gender', 'idx_teachers_gender');
                }
                
                // Index for joining_date (for seniority/experience queries)
                if (!$this->indexExists('teachers', 'idx_teachers_joining_date')) {
                    $table->index('joining_date', 'idx_teachers_joining_date');
                }
                
                // Index for employee_type (Permanent/Contract filtering)
                if (!$this->indexExists('teachers', 'idx_teachers_employee_type')) {
                    $table->index('employee_type', 'idx_teachers_employee_type');
                }
                
                // Index for reporting_manager_id (for hierarchy queries)
                if (!$this->indexExists('teachers', 'idx_teachers_reporting_manager')) {
                    $table->index('reporting_manager_id', 'idx_teachers_reporting_manager');
                }
                
                // Composite index for branch + category + status (common combination)
                if (!$this->indexExists('teachers', 'idx_teachers_branch_cat_status')) {
                    $table->index(['branch_id', 'category_type', 'teacher_status'], 'idx_teachers_branch_cat_status');
                }
            });
        }

        // ============== TEACHER_ATTACHMENTS TABLE INDEXES ==============
        if (Schema::hasTable('teacher_attachments')) {
            Schema::table('teacher_attachments', function (Blueprint $table) {
                // Index for teacher_id + document_type (for fetching specific documents)
                if (!$this->indexExists('teacher_attachments', 'idx_teacher_att_teacher_type')) {
                    $table->index(['teacher_id', 'document_type'], 'idx_teacher_att_teacher_type');
                }
                
                // Index for teacher_id + is_active (for active attachments)
                if (!$this->indexExists('teacher_attachments', 'idx_teacher_att_teacher_active')) {
                    $table->index(['teacher_id', 'is_active'], 'idx_teacher_att_teacher_active');
                }
                
                // Index for document_type alone
                if (!$this->indexExists('teacher_attachments', 'idx_teacher_att_type')) {
                    $table->index('document_type', 'idx_teacher_att_type');
                }
            });
        }

        // ============== DEPARTMENTS TABLE INDEXES (if missing) ==============
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                // Index for name searches
                if (!$this->indexExists('departments', 'idx_departments_name')) {
                    $table->index('name', 'idx_departments_name');
                }
                
                // Index for branch_id + name
                if (!$this->indexExists('departments', 'idx_departments_branch_name')) {
                    $table->index(['branch_id', 'name'], 'idx_departments_branch_name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop teachers indexes
        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table) {
                if ($this->indexExists('teachers', 'idx_teachers_employee_id')) {
                    $table->dropIndex('idx_teachers_employee_id');
                }
                if ($this->indexExists('teachers', 'idx_teachers_branch_status_v2')) {
                    $table->dropIndex('idx_teachers_branch_status_v2');
                }
                if ($this->indexExists('teachers', 'idx_teachers_status_v2')) {
                    $table->dropIndex('idx_teachers_status_v2');
                }
                if ($this->indexExists('teachers', 'idx_teachers_department')) {
                    $table->dropIndex('idx_teachers_department');
                }
                if ($this->indexExists('teachers', 'idx_teachers_branch_dept')) {
                    $table->dropIndex('idx_teachers_branch_dept');
                }
                if ($this->indexExists('teachers', 'idx_teachers_category')) {
                    $table->dropIndex('idx_teachers_category');
                }
                if ($this->indexExists('teachers', 'idx_teachers_designation')) {
                    $table->dropIndex('idx_teachers_designation');
                }
                if ($this->indexExists('teachers', 'idx_teachers_gender')) {
                    $table->dropIndex('idx_teachers_gender');
                }
                if ($this->indexExists('teachers', 'idx_teachers_joining_date')) {
                    $table->dropIndex('idx_teachers_joining_date');
                }
                if ($this->indexExists('teachers', 'idx_teachers_employee_type')) {
                    $table->dropIndex('idx_teachers_employee_type');
                }
                if ($this->indexExists('teachers', 'idx_teachers_reporting_manager')) {
                    $table->dropIndex('idx_teachers_reporting_manager');
                }
                if ($this->indexExists('teachers', 'idx_teachers_branch_cat_status')) {
                    $table->dropIndex('idx_teachers_branch_cat_status');
                }
            });
        }

        // Drop teacher_attachments indexes
        if (Schema::hasTable('teacher_attachments')) {
            Schema::table('teacher_attachments', function (Blueprint $table) {
                if ($this->indexExists('teacher_attachments', 'idx_teacher_att_teacher_type')) {
                    $table->dropIndex('idx_teacher_att_teacher_type');
                }
                if ($this->indexExists('teacher_attachments', 'idx_teacher_att_teacher_active')) {
                    $table->dropIndex('idx_teacher_att_teacher_active');
                }
                if ($this->indexExists('teacher_attachments', 'idx_teacher_att_type')) {
                    $table->dropIndex('idx_teacher_att_type');
                }
            });
        }

        // Drop departments indexes
        if (Schema::hasTable('departments')) {
            Schema::table('departments', function (Blueprint $table) {
                if ($this->indexExists('departments', 'idx_departments_name')) {
                    $table->dropIndex('idx_departments_name');
                }
                if ($this->indexExists('departments', 'idx_departments_branch_name')) {
                    $table->dropIndex('idx_departments_branch_name');
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
