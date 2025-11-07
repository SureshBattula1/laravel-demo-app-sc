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
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'subject_id')) {
            Schema::table('exams', function (Blueprint $table) {
                // Drop the existing foreign key constraint
                $table->dropForeign(['subject_id']);
            });
            
            // Make subject_id nullable
            DB::statement('ALTER TABLE exams MODIFY COLUMN subject_id BIGINT UNSIGNED NULL');
            
            // Add back the foreign key with nullable constraint
            Schema::table('exams', function (Blueprint $table) {
                $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('exams', 'subject_id')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropForeign(['subject_id']);
            });
            
            // Make subject_id required again
            DB::statement('ALTER TABLE exams MODIFY COLUMN subject_id BIGINT UNSIGNED NOT NULL');
            
            Schema::table('exams', function (Blueprint $table) {
                $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            });
        }
    }
};

