<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create separate tables for each user type
     * Better design: users table for authentication, separate tables for each role
     */
    public function up(): void
    {
        // Modify users table to be authentication only
        Schema::table('users', function (Blueprint $table) {
            // Add role for authentication
            if (!Schema::hasColumn('users', 'user_type')) {
                $table->enum('user_type', ['Student', 'Teacher', 'Parent', 'Staff', 'Admin'])->after('email');
            }
            if (!Schema::hasColumn('users', 'user_type_id')) {
                $table->unsignedBigInteger('user_type_id')->nullable()->after('user_type');
            }
        });
        
        // Add index separately with try-catch
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['user_type', 'user_type_id']);
            });
        } catch (\Exception $e) {
            // Index already exists, ignore
        }

        // STUDENTS TABLE
        if (!Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            
            // Admission Details
            $table->string('admission_number')->unique();
            $table->date('admission_date');
            $table->string('roll_number')->nullable();
            $table->string('registration_number')->nullable();
            
            // Academic Details
            $table->string('grade');
            $table->string('section')->nullable();
            $table->string('academic_year');
            $table->string('stream')->nullable(); // Science, Commerce, Arts
            $table->json('elective_subjects')->nullable();
            
            // Personal Details
            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->string('blood_group', 5)->nullable();
            $table->string('religion')->nullable();
            $table->string('category', 50)->nullable(); // General, SC, ST, OBC
            $table->string('nationality')->default('Indian');
            $table->string('mother_tongue')->nullable();
            
            // Address
            $table->text('current_address');
            $table->text('permanent_address')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('country', 100)->default('India');
            $table->string('pincode', 10);
            
            // Parent/Guardian Information
            $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('father_name');
            $table->string('father_occupation')->nullable();
            $table->string('father_phone', 20);
            $table->string('father_email')->nullable();
            $table->decimal('father_annual_income', 12, 2)->nullable();
            
            $table->string('mother_name');
            $table->string('mother_occupation')->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('mother_email')->nullable();
            $table->decimal('mother_annual_income', 12, 2)->nullable();
            
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_phone', 20)->nullable();
            
            // Emergency Contact
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone', 20);
            $table->string('emergency_contact_relation')->nullable();
            
            // Previous Education
            $table->string('previous_school')->nullable();
            $table->string('previous_grade')->nullable();
            $table->decimal('previous_percentage', 5, 2)->nullable();
            $table->text('transfer_certificate_number')->nullable();
            
            // Medical Information
            $table->text('medical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medications')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            
            // Documents
            $table->json('documents')->nullable();
            // {photo, birth_certificate, tc, marksheet, aadhar, etc}
            
            // Status
            $table->enum('student_status', ['Active', 'Graduated', 'Left', 'Suspended', 'Expelled'])->default('Active');
            $table->enum('admission_status', ['Admitted', 'Provisional', 'Cancelled'])->default('Admitted');
            
            // Additional
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('admission_number');
            $table->index(['branch_id', 'grade', 'section', 'academic_year']);
            $table->index('student_status');
            });
        }

        // TEACHERS TABLE
        if (!Schema::hasTable('teachers')) {
            Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            
            // Employment Details
            $table->string('employee_id')->unique();
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->string('designation');
            $table->enum('employee_type', ['Permanent', 'Contract', 'Visiting', 'Temporary'])->default('Permanent');
            
            // Professional Details
            $table->json('qualification'); // Degrees, certifications
            $table->decimal('experience_years', 5, 2)->default(0);
            $table->string('specialization')->nullable();
            $table->string('registration_number')->nullable(); // Teaching council registration
            
            // Teaching Assignment
            $table->json('subjects')->nullable(); // Subject IDs
            $table->json('classes_assigned')->nullable(); // {grade: "10", sections: ["A", "B"]}
            $table->boolean('is_class_teacher')->default(false);
            $table->string('class_teacher_of_grade')->nullable();
            $table->string('class_teacher_of_section')->nullable();
            
            // Personal Details
            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->string('blood_group', 5)->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->default('Indian');
            
            // Address
            $table->text('current_address');
            $table->text('permanent_address')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('pincode', 10);
            
            // Emergency Contact
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone', 20);
            $table->string('emergency_contact_relation')->nullable();
            
            // Salary Details
            $table->string('salary_grade')->nullable();
            $table->decimal('basic_salary', 10, 2);
            
            // Bank Details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code')->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('aadhar_number', 20)->nullable();
            
            // Documents
            $table->json('documents')->nullable();
            
            // Status
            $table->enum('teacher_status', ['Active', 'OnLeave', 'Resigned', 'Retired', 'Terminated'])->default('Active');
            
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('employee_id');
            $table->index(['branch_id', 'teacher_status']);
            });
        }

        // PARENTS TABLE
        if (!Schema::hasTable('parents')) {
            Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Personal Details
            $table->string('first_name');
            $table->string('last_name');
            $table->string('relation_to_student')->nullable(); // Father, Mother, Guardian
            $table->string('occupation')->nullable();
            $table->decimal('annual_income', 12, 2)->nullable();
            
            // Contact
            $table->string('phone', 20);
            $table->string('alternate_phone', 20)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->text('address')->nullable();
            
            // Additional
            $table->boolean('is_primary_contact')->default(true);
            $table->boolean('can_pay_fees')->default(true);
            $table->boolean('can_apply_leave')->default(true);
            $table->boolean('receive_notifications')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            });
        }

        // STAFF TABLE
        if (!Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            
            // Employment Details
            $table->string('employee_id')->unique();
            $table->date('joining_date');
            $table->string('designation');
            $table->enum('staff_type', ['Administrative', 'Accountant', 'Librarian', 'LabAssistant', 'Peon', 'Security', 'Other'])->default('Administrative');
            $table->enum('employee_type', ['Permanent', 'Contract', 'Temporary'])->default('Permanent');
            
            // Personal Details
            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->text('address');
            
            // Salary
            $table->decimal('basic_salary', 10, 2);
            
            // Bank Details
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code')->nullable();
            $table->string('pan_number', 20)->nullable();
            
            // Status
            $table->enum('staff_status', ['Active', 'OnLeave', 'Resigned', 'Retired'])->default('Active');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('employee_id');
            $table->index(['branch_id', 'staff_status']);
            });
        }

        // ADMINS TABLE
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            
            // Admin Details
            $table->string('admin_type')->default('BranchAdmin'); // SuperAdmin, BranchAdmin, etc.
            $table->json('permissions')->nullable(); // Override permissions
            $table->json('accessible_branches')->nullable(); // For multi-branch admins
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();
            
            $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('parents');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('students');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['user_type', 'user_type_id']);
            $table->dropColumn(['user_type', 'user_type_id']);
        });
    }
};

