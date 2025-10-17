<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                // Add columns without referencing other columns that might not exist
            
            if (!Schema::hasColumn('students', 'permanent_address')) {
                $table->text('permanent_address')->nullable();
            }
            
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
            
            if (!Schema::hasColumn('students', 'elective_subjects')) {
                $table->json('elective_subjects')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'father_email')) {
                $table->string('father_email')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'father_occupation')) {
                $table->string('father_occupation')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'father_annual_income')) {
                $table->decimal('father_annual_income', 12, 2)->nullable();
            }
            
            if (!Schema::hasColumn('students', 'mother_email')) {
                $table->string('mother_email')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'mother_occupation')) {
                $table->string('mother_occupation')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'mother_annual_income')) {
                $table->decimal('mother_annual_income', 12, 2)->nullable();
            }
            
            if (!Schema::hasColumn('students', 'guardian_name')) {
                $table->string('guardian_name')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'guardian_relation')) {
                $table->string('guardian_relation')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'guardian_phone')) {
                $table->string('guardian_phone', 20)->nullable();
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
            
            if (!Schema::hasColumn('students', 'previous_percentage')) {
                $table->decimal('previous_percentage', 5, 2)->nullable();
            }
            
            if (!Schema::hasColumn('students', 'transfer_certificate_number')) {
                $table->text('transfer_certificate_number')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'medical_history')) {
                $table->text('medical_history')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'allergies')) {
                $table->text('allergies')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'medications')) {
                $table->text('medications')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'height_cm')) {
                $table->decimal('height_cm', 5, 2)->nullable();
            }
            
            if (!Schema::hasColumn('students', 'weight_kg')) {
                $table->decimal('weight_kg', 5, 2)->nullable();
            }
            
            if (!Schema::hasColumn('students', 'documents')) {
                $table->json('documents')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'admission_status')) {
                $table->enum('admission_status', ['Admitted', 'Provisional', 'Cancelled'])->default('Admitted');
            }
            
            if (!Schema::hasColumn('students', 'remarks')) {
                $table->text('remarks')->nullable();
            }
            
            if (!Schema::hasColumn('students', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('students', 'registration_number')) {
                $table->string('registration_number')->nullable();
            }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                $columns = [
                'permanent_address', 'religion', 'category', 'nationality', 'mother_tongue',
                'stream', 'elective_subjects', 'father_email', 'father_occupation', 'father_annual_income',
                'mother_email', 'mother_occupation', 'mother_annual_income', 'guardian_name', 
                'guardian_relation', 'guardian_phone', 'emergency_contact_relation', 'previous_school', 
                'previous_grade', 'previous_percentage', 'transfer_certificate_number', 'medical_history', 
                'allergies', 'medications', 'height_cm', 'weight_kg', 'documents', 'admission_status', 
                'remarks', 'parent_id', 'registration_number'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
            });
        }
    }
};

