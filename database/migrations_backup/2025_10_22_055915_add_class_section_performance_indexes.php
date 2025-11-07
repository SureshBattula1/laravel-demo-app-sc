<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for class/section/grade tables
     * These are critical APIs used on every page!
     */
    public function up(): void
    {
        // ============== GRADES TABLE INDEXES ==============
        if (Schema::hasTable('grades')) {
            Schema::table('grades', function (Blueprint $table) {
                // Index for is_active filtering (very common)
                if (!$this->indexExists('grades', 'idx_grades_active')) {
                    $table->index('is_active', 'idx_grades_active');
                }
                
                // Index for value lookups
                if (!$this->indexExists('grades', 'idx_grades_value')) {
                    $table->index('value', 'idx_grades_value');
                }
            });
        }

        // ============== SECTIONS TABLE INDEXES ==============
        if (Schema::hasTable('sections')) {
            Schema::table('sections', function (Blueprint $table) {
                // Composite index for branch + grade_level (very common filter)
                if (!$this->indexExists('sections', 'idx_sections_branch_grade')) {
                    $table->index(['branch_id', 'grade_level'], 'idx_sections_branch_grade');
                }
                
                // Composite index for branch + grade_level + is_active
                if (!$this->indexExists('sections', 'idx_sections_branch_grade_active')) {
                    $table->index(['branch_id', 'grade_level', 'is_active'], 'idx_sections_branch_grade_active');
                }
                
                // Index for grade_level alone (for dropdown filtering)
                if (!$this->indexExists('sections', 'idx_sections_grade_level')) {
                    $table->index('grade_level', 'idx_sections_grade_level');
                }
                
                // Index for name lookups
                if (!$this->indexExists('sections', 'idx_sections_name')) {
                    $table->index('name', 'idx_sections_name');
                }
                
                // Index for is_active filtering
                if (!$this->indexExists('sections', 'idx_sections_active')) {
                    $table->index('is_active', 'idx_sections_active');
                }
                
                // Index for class_teacher_id
                if (!$this->indexExists('sections', 'idx_sections_teacher')) {
                    $table->index('class_teacher_id', 'idx_sections_teacher');
                }
            });
        }

        // ============== CLASSES TABLE INDEXES ==============
        if (Schema::hasTable('classes')) {
            Schema::table('classes', function (Blueprint $table) {
                // Composite index for branch + grade + section + academic_year (unique class identifier)
                if (!$this->indexExists('classes', 'idx_classes_branch_grade_section_year')) {
                    $table->index(['branch_id', 'grade', 'section', 'academic_year'], 'idx_classes_branch_grade_section_year');
                }
                
                // Composite index for branch + grade
                if (!$this->indexExists('classes', 'idx_classes_branch_grade')) {
                    $table->index(['branch_id', 'grade'], 'idx_classes_branch_grade');
                }
                
                // Composite index for grade + section
                if (!$this->indexExists('classes', 'idx_classes_grade_section')) {
                    $table->index(['grade', 'section'], 'idx_classes_grade_section');
                }
                
                // Index for academic_year
                if (!$this->indexExists('classes', 'idx_classes_academic_year')) {
                    $table->index('academic_year', 'idx_classes_academic_year');
                }
                
                // Index for is_active
                if (!$this->indexExists('classes', 'idx_classes_active')) {
                    $table->index('is_active', 'idx_classes_active');
                }
                
                // Index for class_teacher_id
                if (!$this->indexExists('classes', 'idx_classes_teacher')) {
                    $table->index('class_teacher_id', 'idx_classes_teacher');
                }
            });
        }

        // ============== OPTIMIZE STUDENTS TABLE FOR CLASS/SECTION QUERIES ==============
        // These indexes help with the frequent grade/section lookups
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                // Composite index for counting students by section (for actual_strength calculations)
                if (!$this->indexExists('students', 'idx_students_branch_grade_section_status')) {
                    $table->index(['branch_id', 'grade', 'section', 'student_status'], 'idx_students_branch_grade_section_status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop grades indexes
        if (Schema::hasTable('grades')) {
            Schema::table('grades', function (Blueprint $table) {
                if ($this->indexExists('grades', 'idx_grades_active')) {
                    $table->dropIndex('idx_grades_active');
                }
                if ($this->indexExists('grades', 'idx_grades_value')) {
                    $table->dropIndex('idx_grades_value');
                }
            });
        }

        // Drop sections indexes
        if (Schema::hasTable('sections')) {
            Schema::table('sections', function (Blueprint $table) {
                if ($this->indexExists('sections', 'idx_sections_branch_grade')) {
                    $table->dropIndex('idx_sections_branch_grade');
                }
                if ($this->indexExists('sections', 'idx_sections_branch_grade_active')) {
                    $table->dropIndex('idx_sections_branch_grade_active');
                }
                if ($this->indexExists('sections', 'idx_sections_grade_level')) {
                    $table->dropIndex('idx_sections_grade_level');
                }
                if ($this->indexExists('sections', 'idx_sections_name')) {
                    $table->dropIndex('idx_sections_name');
                }
                if ($this->indexExists('sections', 'idx_sections_active')) {
                    $table->dropIndex('idx_sections_active');
                }
                if ($this->indexExists('sections', 'idx_sections_teacher')) {
                    $table->dropIndex('idx_sections_teacher');
                }
            });
        }

        // Drop classes indexes
        if (Schema::hasTable('classes')) {
            Schema::table('classes', function (Blueprint $table) {
                if ($this->indexExists('classes', 'idx_classes_branch_grade_section_year')) {
                    $table->dropIndex('idx_classes_branch_grade_section_year');
                }
                if ($this->indexExists('classes', 'idx_classes_branch_grade')) {
                    $table->dropIndex('idx_classes_branch_grade');
                }
                if ($this->indexExists('classes', 'idx_classes_grade_section')) {
                    $table->dropIndex('idx_classes_grade_section');
                }
                if ($this->indexExists('classes', 'idx_classes_academic_year')) {
                    $table->dropIndex('idx_classes_academic_year');
                }
                if ($this->indexExists('classes', 'idx_classes_active')) {
                    $table->dropIndex('idx_classes_active');
                }
                if ($this->indexExists('classes', 'idx_classes_teacher')) {
                    $table->dropIndex('idx_classes_teacher');
                }
            });
        }

        // Drop students indexes
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if ($this->indexExists('students', 'idx_students_branch_grade_section_status')) {
                    $table->dropIndex('idx_students_branch_grade_section_status');
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
