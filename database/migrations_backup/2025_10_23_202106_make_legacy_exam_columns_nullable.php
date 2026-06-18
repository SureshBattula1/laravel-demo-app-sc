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
            Schema::table('exams', function (Blueprint $table) {
                // Make legacy columns nullable
                $columnsToMakeNullable = [
                    'grade_level',
                    'section',
                    'date',
                    'start_time',
                    'end_time',
                    'duration',
                    'room',
                    'instructions'
                ];
                
                foreach ($columnsToMakeNullable as $column) {
                    if (Schema::hasColumn('exams', $column)) {
                        switch ($column) {
                            case 'grade_level':
                            case 'section':
                            case 'room':
                                DB::statement("ALTER TABLE exams MODIFY COLUMN {$column} VARCHAR(255) NULL");
                                break;
                            case 'date':
                                DB::statement("ALTER TABLE exams MODIFY COLUMN {$column} DATE NULL");
                                break;
                            case 'start_time':
                            case 'end_time':
                                DB::statement("ALTER TABLE exams MODIFY COLUMN {$column} TIME NULL");
                                break;
                            case 'duration':
                                DB::statement("ALTER TABLE exams MODIFY COLUMN {$column} INT NULL");
                                break;
                            case 'instructions':
                                DB::statement("ALTER TABLE exams MODIFY COLUMN {$column} TEXT NULL");
                                break;
                        }
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('exams')) {
            // This migration is to support legacy structure, so we can leave the down method empty
            // or implement reversal if needed
        }
    }
};

