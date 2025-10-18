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
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                // Add permanent_address if it doesn't exist
                if (!Schema::hasColumn('students', 'permanent_address')) {
                    $table->text('permanent_address')->nullable();
                }
                
                // Add other commonly missing columns
                if (!Schema::hasColumn('students', 'religion')) {
                    $table->string('religion')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'category')) {
                    $table->string('category', 50)->nullable();
                }
                
                if (!Schema::hasColumn('students', 'nationality')) {
                    $table->string('nationality')->default('Indian');
                }
                
                if (!Schema::hasColumn('students', 'mother_tongue')) {
                    $table->string('mother_tongue')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'stream')) {
                    $table->string('stream')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'father_email')) {
                    $table->string('father_email')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'father_occupation')) {
                    $table->string('father_occupation')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'mother_email')) {
                    $table->string('mother_email')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'mother_occupation')) {
                    $table->string('mother_occupation')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'emergency_contact_relation')) {
                    $table->string('emergency_contact_relation')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'previous_school')) {
                    $table->string('previous_school')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'previous_grade')) {
                    $table->string('previous_grade')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'medical_history')) {
                    $table->text('medical_history')->nullable();
                }
                
                if (!Schema::hasColumn('students', 'allergies')) {
                    $table->text('allergies')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't remove columns on rollback to avoid data loss
    }
};

