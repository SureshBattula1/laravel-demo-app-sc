<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Complete Teachers System
     * This migration creates everything needed for the teacher module in one place
     */
    public function up(): void
    {
        // ============================================
        // TEACHERS TABLE - Complete with all fields
        // ============================================
        if (!Schema::hasTable('teachers')) {
            Schema::create('teachers', function (Blueprint $table) {
                $table->id();
                
                // Foreign Keys
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
                $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
                $table->unsignedBigInteger('reporting_manager_id')->nullable()->comment('References another teacher');
                
                // Employment Details
                $table->string('employee_id')->unique();
                $table->enum('category_type', ['Teaching', 'Non-Teaching'])->default('Teaching');
                $table->date('joining_date');
                $table->date('leaving_date')->nullable();
                $table->string('designation');
                $table->enum('employee_type', ['Permanent', 'Contract', 'Visiting', 'Temporary'])->default('Permanent');
                
                // Professional Details
                $table->json('qualification')->nullable()->comment('Degrees, certifications');
                $table->decimal('experience_years', 5, 2)->default(0);
                $table->string('specialization')->nullable();
                $table->string('registration_number')->nullable()->comment('Teaching council registration');
                
                // Teaching Assignment
                $table->json('subjects')->nullable()->comment('Subject IDs or names');
                $table->json('classes_assigned')->nullable()->comment('Grade and sections assigned');
                $table->boolean('is_class_teacher')->default(false);
                $table->string('class_teacher_of_grade')->nullable();
                $table->string('class_teacher_of_section')->nullable();
                
                // Personal Details
                $table->date('date_of_birth');
                $table->enum('gender', ['Male', 'Female', 'Other']);
                $table->string('blood_group', 5)->nullable();
                $table->string('religion')->nullable();
                $table->string('nationality')->default('Indian');
                
                // Address Information
                $table->text('current_address');
                $table->text('permanent_address')->nullable();
                $table->string('city', 100);
                $table->string('state', 100);
                $table->string('pincode', 10);
                
                // Emergency Contact
                $table->string('emergency_contact_name');
                $table->string('emergency_contact_phone', 20);
                $table->string('emergency_contact_relation')->nullable();
                
                // Salary & Financial Details
                $table->string('salary_grade')->nullable();
                $table->decimal('basic_salary', 10, 2);
                
                // Bank Details
                $table->string('bank_name')->nullable();
                $table->string('bank_account_number')->nullable();
                $table->string('bank_ifsc_code')->nullable();
                $table->string('pan_number', 20)->nullable();
                $table->string('aadhar_number', 20)->nullable();
                
                // Extended Profile (JSON for additional flexible fields)
                $table->json('extended_profile')->nullable()->comment('Additional teacher profile data stored as JSON');
                
                // Documents
                $table->json('documents')->nullable()->comment('Document paths and metadata');
                
                // Status & Metadata
                $table->enum('teacher_status', ['Active', 'OnLeave', 'Resigned', 'Retired', 'Terminated'])->default('Active');
                $table->text('remarks')->nullable();
                
                // Timestamps
                $table->timestamps();
                $table->softDeletes();
                
                // ============================================
                // INDEXES - All performance indexes
                // ============================================
                
                // Primary lookup indexes
                $table->index('employee_id', 'idx_teachers_employee_id');
                $table->index('user_id', 'idx_teachers_user_id');
                $table->index('branch_id', 'idx_teachers_branch_id');
                $table->index('department_id', 'idx_teachers_department_id');
                
                // Status and type indexes
                $table->index('teacher_status', 'idx_teachers_status');
                $table->index('category_type', 'idx_teachers_category');
                $table->index('employee_type', 'idx_teachers_emp_type');
                
                // Common filter indexes
                $table->index('designation', 'idx_teachers_designation');
                $table->index('gender', 'idx_teachers_gender');
                $table->index('joining_date', 'idx_teachers_joining');
                $table->index('reporting_manager_id', 'idx_teachers_manager');
                
                // Composite indexes for common queries
                $table->index(['branch_id', 'teacher_status'], 'idx_teachers_branch_status');
                $table->index(['branch_id', 'department_id'], 'idx_teachers_branch_dept');
                $table->index(['branch_id', 'category_type'], 'idx_teachers_branch_cat');
                $table->index(['branch_id', 'category_type', 'teacher_status'], 'idx_teachers_branch_cat_status');
                $table->index(['department_id', 'teacher_status'], 'idx_teachers_dept_status');
                $table->index(['is_class_teacher', 'branch_id'], 'idx_teachers_class_teacher');
                
                // Soft delete index
                $table->index('deleted_at', 'idx_teachers_deleted');
            });
        } else {
            // If table exists, add missing columns safely
            Schema::table('teachers', function (Blueprint $table) {
                if (!Schema::hasColumn('teachers', 'category_type')) {
                    $table->enum('category_type', ['Teaching', 'Non-Teaching'])->default('Teaching');
                }
                if (!Schema::hasColumn('teachers', 'department_id')) {
                    $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
                }
                if (!Schema::hasColumn('teachers', 'reporting_manager_id')) {
                    $table->unsignedBigInteger('reporting_manager_id')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'extended_profile')) {
                    $table->json('extended_profile')->nullable()->comment('Additional teacher profile data');
                }
                if (!Schema::hasColumn('teachers', 'bank_name')) {
                    $table->string('bank_name')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'bank_account_number')) {
                    $table->string('bank_account_number')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'bank_ifsc_code')) {
                    $table->string('bank_ifsc_code')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'pan_number')) {
                    $table->string('pan_number', 20)->nullable();
                }
                if (!Schema::hasColumn('teachers', 'aadhar_number')) {
                    $table->string('aadhar_number', 20)->nullable();
                }
                if (!Schema::hasColumn('teachers', 'salary_grade')) {
                    $table->string('salary_grade')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'specialization')) {
                    $table->string('specialization')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'registration_number')) {
                    $table->string('registration_number')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'class_teacher_of_grade')) {
                    $table->string('class_teacher_of_grade')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'class_teacher_of_section')) {
                    $table->string('class_teacher_of_section')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'leaving_date')) {
                    $table->date('leaving_date')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'documents')) {
                    $table->json('documents')->nullable();
                }
                if (!Schema::hasColumn('teachers', 'remarks')) {
                    $table->text('remarks')->nullable();
                }
            });
        }

        // ============================================
        // TEACHER_ATTACHMENTS TABLE
        // ============================================
        if (!Schema::hasTable('teacher_attachments')) {
            Schema::create('teacher_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
                
                // File Information
                $table->string('document_type', 50)->comment('profile_picture, resume, joining_letter, etc.');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('file_type', 50)->comment('jpg, png, pdf, etc.');
                $table->integer('file_size')->comment('Size in bytes');
                $table->string('original_name');
                $table->text('description')->nullable();
                
                // Status & Metadata
                $table->boolean('is_active')->default(true);
                $table->string('uploaded_by')->nullable();
                
                // Timestamps
                $table->timestamps();
                $table->softDeletes();
                
                // Indexes
                $table->index('teacher_id', 'idx_teacher_att_teacher');
                $table->index('document_type', 'idx_teacher_att_type');
                $table->index(['teacher_id', 'document_type'], 'idx_teacher_att_teacher_type');
                $table->index(['teacher_id', 'is_active'], 'idx_teacher_att_teacher_active');
                $table->index(['teacher_id', 'document_type', 'is_active'], 'idx_teacher_att_full');
                $table->index('deleted_at', 'idx_teacher_att_deleted');
            });
        }

        // ============================================
        // TEACHER ATTENDANCE TABLE (if needed)
        // ============================================
        if (!Schema::hasTable('teacher_attendance')) {
            Schema::create('teacher_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
                $table->date('date');
                $table->enum('status', ['Present', 'Absent', 'Late', 'HalfDay', 'OnLeave'])->default('Present');
                $table->time('check_in_time')->nullable();
                $table->time('check_out_time')->nullable();
                $table->string('leave_type', 50)->nullable()->comment('Sick, Casual, Earned, etc.');
                $table->text('remarks')->nullable();
                $table->string('marked_by')->nullable();
                
                $table->timestamps();
                
                // Indexes
                $table->unique(['teacher_id', 'date'], 'idx_teacher_att_unique');
                $table->index('branch_id', 'idx_teacher_att_branch');
                $table->index('date', 'idx_teacher_att_date');
                $table->index('status', 'idx_teacher_att_status');
                $table->index(['teacher_id', 'date'], 'idx_teacher_att_teacher_date');
                $table->index(['branch_id', 'date'], 'idx_teacher_att_branch_date');
                $table->index(['date', 'status'], 'idx_teacher_att_date_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order to respect foreign key constraints
        Schema::dropIfExists('teacher_attendance');
        Schema::dropIfExists('teacher_attachments');
        Schema::dropIfExists('teachers');
    }
};

