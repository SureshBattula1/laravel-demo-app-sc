<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Check if start_date column exists
            if (!Schema::hasColumn('exams', 'start_date')) {
                $table->date('start_date')->nullable()->after('academic_year');
            }
            
            // Check if end_date column exists
            if (!Schema::hasColumn('exams', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            
            // Check if exam_type column exists (might be called 'type')
            if (!Schema::hasColumn('exams', 'exam_type')) {
                if (Schema::hasColumn('exams', 'type')) {
                    // Rename type to exam_type
                    $table->renameColumn('type', 'exam_type');
                } else {
                    $table->string('exam_type')->nullable()->after('name');
                }
            }
            
            // Check if description column exists
            if (!Schema::hasColumn('exams', 'description')) {
                $table->text('description')->nullable()->after('passing_marks');
            }
            
            // Check if created_by column exists
            if (!Schema::hasColumn('exams', 'created_by')) {
                $table->string('created_by')->nullable()->after('is_active');
            }
            
            // Check if updated_by column exists
            if (!Schema::hasColumn('exams', 'updated_by')) {
                $table->string('updated_by')->nullable()->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'start_date')) {
                $table->dropColumn('start_date');
            }
            
            if (Schema::hasColumn('exams', 'end_date')) {
                $table->dropColumn('end_date');
            }
            
            if (Schema::hasColumn('exams', 'description')) {
                $table->dropColumn('description');
            }
            
            if (Schema::hasColumn('exams', 'created_by')) {
                $table->dropColumn('created_by');
            }
            
            if (Schema::hasColumn('exams', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
        });
    }
};

