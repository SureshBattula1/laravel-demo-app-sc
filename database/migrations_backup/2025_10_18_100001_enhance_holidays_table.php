<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Enhance holidays table
     */
    public function up(): void
    {
        if (Schema::hasTable('holidays')) {
            // Add missing columns if they don't exist
            Schema::table('holidays', function (Blueprint $table) {
                if (!Schema::hasColumn('holidays', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
                }
                
                if (!Schema::hasColumn('holidays', 'title')) {
                    $table->string('title');
                }
                
                if (!Schema::hasColumn('holidays', 'description')) {
                    $table->text('description')->nullable();
                }
                
                if (!Schema::hasColumn('holidays', 'start_date')) {
                    $table->date('start_date');
                }
                
                if (!Schema::hasColumn('holidays', 'end_date')) {
                    $table->date('end_date');
                }
                
                if (!Schema::hasColumn('holidays', 'type')) {
                    $table->enum('type', ['National', 'State', 'School', 'Optional', 'Restricted'])->default('School');
                }
                
                if (!Schema::hasColumn('holidays', 'color')) {
                    $table->string('color', 20)->nullable()->comment('For calendar visualization');
                }
                
                if (!Schema::hasColumn('holidays', 'is_recurring')) {
                    $table->boolean('is_recurring')->default(false);
                }
                
                if (!Schema::hasColumn('holidays', 'academic_year')) {
                    $table->string('academic_year', 20)->nullable();
                }
                
                if (!Schema::hasColumn('holidays', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                
                if (!Schema::hasColumn('holidays', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                }
            });
            
            // Add indexes
            try {
                Schema::table('holidays', function (Blueprint $table) {
                    $table->index(['start_date', 'end_date']);
                    $table->index(['type', 'is_active']);
                });
            } catch (\Exception $e) {
                // Indexes might already exist
            }
        } else {
            // Create holidays table from scratch
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('type', ['National', 'State', 'School', 'Optional', 'Restricted'])->default('School');
                $table->string('color', 20)->nullable()->comment('For calendar visualization');
                $table->boolean('is_recurring')->default(false);
                $table->string('academic_year', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index(['start_date', 'end_date']);
                $table->index(['type', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop the table to avoid data loss
    }
};

