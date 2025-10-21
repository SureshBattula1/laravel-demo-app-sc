<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add only critical missing fields using JSON to avoid row size limit
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Add only the most critical field: category_type
            if (!Schema::hasColumn('teachers', 'category_type')) {
                $table->enum('category_type', ['Teaching', 'Non-Teaching'])->default('Teaching');
            }
            
            // Add department relationship
            if (!Schema::hasColumn('teachers', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable();
            }
            
            // Add reporting manager relationship  
            if (!Schema::hasColumn('teachers', 'reporting_manager_id')) {
                $table->unsignedBigInteger('reporting_manager_id')->nullable();
            }
            
            // Store ALL other extended data in a single JSON column
            if (!Schema::hasColumn('teachers', 'extended_profile')) {
                $table->json('extended_profile')->nullable()->comment('All additional teacher profile data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'category_type')) {
                $table->dropColumn('category_type');
            }
            if (Schema::hasColumn('teachers', 'department_id')) {
                $table->dropColumn('department_id');
            }
            if (Schema::hasColumn('teachers', 'reporting_manager_id')) {
                $table->dropColumn('reporting_manager_id');
            }
            if (Schema::hasColumn('teachers', 'extended_profile')) {
                $table->dropColumn('extended_profile');
            }
        });
    }
};
