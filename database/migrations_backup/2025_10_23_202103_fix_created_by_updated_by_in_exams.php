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
        Schema::table('exams', function (Blueprint $table) {
            // Change created_by and updated_by from string to foreignId
            if (Schema::hasColumn('exams', 'created_by')) {
                DB::statement('ALTER TABLE exams MODIFY COLUMN created_by BIGINT UNSIGNED NULL');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            }
            
            if (Schema::hasColumn('exams', 'updated_by')) {
                DB::statement('ALTER TABLE exams MODIFY COLUMN updated_by BIGINT UNSIGNED NULL');
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'created_by')) {
                $table->dropForeign(['created_by']);
                DB::statement('ALTER TABLE exams MODIFY COLUMN created_by VARCHAR(255) NULL');
            }
            
            if (Schema::hasColumn('exams', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                DB::statement('ALTER TABLE exams MODIFY COLUMN updated_by VARCHAR(255) NULL');
            }
        });
    }
};

