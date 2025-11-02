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
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'exam_type')) {
            // Check if exam_type is an enum
            $columnInfo = DB::select("SHOW COLUMNS FROM exams WHERE Field = 'exam_type'");
            if (!empty($columnInfo)) {
                $columnDef = $columnInfo[0]->Type;
                
                // If it's an enum, convert it to string
                if (strpos($columnDef, 'enum') !== false) {
                    DB::statement('ALTER TABLE exams MODIFY COLUMN exam_type VARCHAR(255)');
                }
            }
        }
        
        // If exam_type doesn't exist, create it
        if (!Schema::hasColumn('exams', 'exam_type')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->string('exam_type')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to enum if needed
        if (Schema::hasColumn('exams', 'exam_type')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn('exam_type');
            });
        }
    }
};

