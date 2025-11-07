<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Modify holidays table for enhanced structure
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            // Rename 'name' to 'title' if needed
            if (!Schema::hasColumn('holidays', 'title')) {
                $table->renameColumn('name', 'title');
            }
            
            // Modify branch_id to be nullable (for all-branches holidays)
            $table->foreignId('branch_id')->nullable()->change();
            
            // Add new date columns
            if (!Schema::hasColumn('holidays', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            
            if (!Schema::hasColumn('holidays', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            
            // Modify type enum to include new values
            DB::statement("ALTER TABLE holidays MODIFY type ENUM('National', 'State', 'School', 'Optional', 'Restricted', 'Religious', 'Regional') DEFAULT 'School'");
            
            // Add new columns
            if (!Schema::hasColumn('holidays', 'color')) {
                $table->string('color', 20)->nullable();
            }
            
            if (!Schema::hasColumn('holidays', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false);
            }
            
            if (!Schema::hasColumn('holidays', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            if (!Schema::hasColumn('holidays', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            }
        });
        
        // Copy 'date' to 'start_date' and 'end_date' for existing records
        DB::statement('UPDATE holidays SET start_date = date, end_date = date WHERE start_date IS NULL OR end_date IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't reverse to avoid data loss
    }
};

