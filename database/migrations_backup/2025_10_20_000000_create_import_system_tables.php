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
        // Import Histories - Track all import operations
        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 50)->unique();
            $table->enum('entity_type', ['student', 'teacher', 'staff', 'department'])->index();
            
            // User & Branch context
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            
            // File metadata
            $table->string('file_name');
            $table->integer('file_size');
            
            // Import context (for students: grade, section, academic_year)
            $table->json('import_context')->nullable();
            
            // Statistics
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            
            // Status tracking
            $table->enum('status', ['uploaded', 'validating', 'validated', 'importing', 'completed', 'failed', 'cancelled'])->default('uploaded');
            
            // Timestamps for each phase
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('validation_started_at')->nullable();
            $table->timestamp('validation_completed_at')->nullable();
            $table->timestamp('import_started_at')->nullable();
            $table->timestamp('import_completed_at')->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->string('error_report_path')->nullable(); // Path to downloadable error Excel
            
            $table->timestamps();
            
            $table->index(['uploaded_by', 'entity_type']);
            $table->index('status');
            $table->index('created_at');
        });

        // Student Imports Staging Table
        Schema::create('student_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 50)->index();
            $table->integer('row_number')->index();
            
            // Import context
            $table->foreignId('branch_id')->nullable();
            $table->string('grade')->nullable();
            $table->string('section')->nullable();
            $table->string('academic_year')->nullable();
            
            // User fields
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            // Student admission details
            $table->string('admission_number')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('roll_number')->nullable();
            $table->string('registration_number')->nullable();
            
            // Academic details (can override context)
            $table->string('grade_override')->nullable();
            $table->string('section_override')->nullable();
            $table->string('academic_year_override')->nullable();
            $table->string('stream')->nullable();
            
            // Personal details
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('religion')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('nationality')->nullable();
            $table->string('mother_tongue')->nullable();
            
            // Address
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            
            // Parent information
            $table->string('father_name')->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('father_email')->nullable();
            $table->string('father_occupation')->nullable();
            $table->decimal('father_annual_income', 12, 2)->nullable();
            
            $table->string('mother_name')->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_email')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->decimal('mother_annual_income', 12, 2)->nullable();
            
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_phone', 20)->nullable();
            
            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation')->nullable();
            
            // Previous education
            $table->string('previous_school')->nullable();
            $table->string('previous_grade')->nullable();
            $table->decimal('previous_percentage', 5, 2)->nullable();
            $table->string('transfer_certificate_number')->nullable();
            
            // Medical information
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medications')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            
            // Additional
            $table->string('password')->nullable();
            $table->text('remarks')->nullable();
            
            // Validation metadata
            $table->enum('validation_status', ['pending', 'valid', 'invalid'])->default('pending')->index();
            $table->json('validation_errors')->nullable(); // Array of error messages
            $table->json('validation_warnings')->nullable(); // Array of warning messages
            
            // Import tracking
            $table->boolean('imported_to_production')->default(false)->index();
            $table->unsignedBigInteger('imported_user_id')->nullable(); // Created user.id
            $table->unsignedBigInteger('imported_student_id')->nullable(); // Created student.id
            $table->timestamp('imported_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['batch_id', 'validation_status']);
            $table->index(['batch_id', 'imported_to_production']);
            $table->foreign('batch_id')->references('batch_id')->on('import_histories')->onDelete('cascade');
        });

        // Teacher Imports Staging Table
        Schema::create('teacher_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 50)->index();
            $table->integer('row_number')->index();
            
            // Import context
            $table->foreignId('branch_id')->nullable();
            
            // User fields
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            // Employment details
            $table->string('employee_id')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->string('designation')->nullable();
            $table->enum('employee_type', ['Permanent', 'Contract', 'Visiting', 'Temporary'])->nullable();
            
            // Professional details
            $table->text('qualification')->nullable(); // JSON or text
            $table->decimal('experience_years', 5, 2)->nullable();
            $table->string('specialization')->nullable();
            $table->string('registration_number')->nullable();
            
            // Teaching assignment
            $table->text('subjects')->nullable(); // JSON array
            $table->text('classes_assigned')->nullable(); // JSON
            $table->boolean('is_class_teacher')->default(false);
            $table->string('class_teacher_of_grade')->nullable();
            $table->string('class_teacher_of_section')->nullable();
            
            // Personal details
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->nullable();
            
            // Address
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            
            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation')->nullable();
            
            // Salary & Bank details
            $table->string('salary_grade')->nullable();
            $table->decimal('basic_salary', 10, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code')->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('aadhar_number', 20)->nullable();
            
            // Additional
            $table->string('password')->nullable();
            $table->text('remarks')->nullable();
            
            // Validation metadata
            $table->enum('validation_status', ['pending', 'valid', 'invalid'])->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            
            // Import tracking
            $table->boolean('imported_to_production')->default(false)->index();
            $table->unsignedBigInteger('imported_user_id')->nullable();
            $table->unsignedBigInteger('imported_teacher_id')->nullable();
            $table->timestamp('imported_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['batch_id', 'validation_status']);
            $table->index(['batch_id', 'imported_to_production']);
            $table->foreign('batch_id')->references('batch_id')->on('import_histories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_imports');
        Schema::dropIfExists('student_imports');
        Schema::dropIfExists('import_histories');
    }
};

