<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('exams')) {
            // Make total_marks and passing_marks nullable
            // These are now managed at the exam_schedules level
            if (Schema::hasColumn('exams', 'total_marks')) {
                DB::statement('ALTER TABLE exams MODIFY COLUMN total_marks DECIMAL(8,2) NULL');
            }
            
            if (Schema::hasColumn('exams', 'passing_marks')) {
                DB::statement('ALTER TABLE exams MODIFY COLUMN passing_marks DECIMAL(8,2) NULL');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('exams')) {
            // Revert back to non-nullable (if needed)
            if (Schema::hasColumn('exams', 'total_marks')) {
                DB::statement('ALTER TABLE exams MODIFY COLUMN total_marks DECIMAL(8,2) NOT NULL DEFAULT 100');
            }
            
            if (Schema::hasColumn('exams', 'passing_marks')) {
                DB::statement('ALTER TABLE exams MODIFY COLUMN passing_marks DECIMAL(8,2) NOT NULL DEFAULT 40');
            }
        }
    }
};
