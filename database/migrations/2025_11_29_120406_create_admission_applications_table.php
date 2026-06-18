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
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('application_number')->unique();
            $table->date('application_date');
            $table->string('academic_year');
            $table->string('applying_for_grade');
            $table->string('applying_for_section')->nullable();
            
            // Student Personal Information
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->string('blood_group')->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->default('Indian');
            $table->string('category')->nullable();
            $table->string('mother_tongue')->nullable();
            
            // Contact Information
            $table->string('email');
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            
            // Address Information
            $table->text('current_address');
            $table->string('current_city');
            $table->string('current_state');
            $table->string('current_country')->default('India');
            $table->string('current_pincode');
            $table->text('permanent_address')->nullable();
            $table->string('permanent_city')->nullable();
            $table->string('permanent_state')->nullable();
            $table->string('permanent_country')->default('India');
            $table->string('permanent_pincode')->nullable();
            
            // Father Information
            $table->string('father_name');
            $table->string('father_phone');
            $table->string('father_email')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_qualification')->nullable();
            $table->decimal('father_annual_income', 10, 2)->nullable();
            
            // Mother Information
            $table->string('mother_name');
            $table->string('mother_phone')->nullable();
            $table->string('mother_email')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_qualification')->nullable();
            $table->decimal('mother_annual_income', 10, 2)->nullable();
            
            // Guardian Information
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();
            $table->text('guardian_address')->nullable();
            
            // Previous Education
            $table->string('previous_school')->nullable();
            $table->string('previous_grade')->nullable();
            $table->string('previous_school_board')->nullable();
            $table->decimal('previous_percentage', 5, 2)->nullable();
            $table->string('transfer_certificate_number')->nullable();
            $table->date('tc_date')->nullable();
            
            // Application Status
            $table->enum('application_status', ['Applied', 'Shortlisted', 'Rejected', 'Admitted', 'Waitlisted'])->default('Applied');
            
            // Application Fee
            $table->boolean('application_fee_paid')->default(false);
            $table->decimal('application_fee_amount', 10, 2)->nullable();
            $table->date('application_fee_payment_date')->nullable();
            $table->string('application_fee_receipt_number')->nullable();
            
            // Entrance Test
            $table->boolean('entrance_test_required')->default(false);
            $table->date('entrance_test_date')->nullable();
            $table->decimal('entrance_test_score', 5, 2)->nullable();
            $table->enum('entrance_test_result', ['Pass', 'Fail', 'Pending'])->nullable();
            
            // Interview
            $table->boolean('interview_required')->default(false);
            $table->date('interview_date')->nullable();
            $table->decimal('interview_score', 5, 2)->nullable();
            $table->enum('interview_result', ['Pass', 'Fail', 'Pending'])->nullable();
            
            // Admission Decision
            $table->enum('admission_decision', ['Approved', 'Rejected', 'Waitlisted', 'Pending'])->nullable();
            $table->date('admission_decision_date')->nullable();
            $table->string('admission_offer_letter')->nullable();
            $table->date('admission_validity_date')->nullable();
            
            // Registration Fee
            $table->boolean('registration_fee_paid')->default(false);
            $table->decimal('registration_fee_amount', 10, 2)->nullable();
            $table->date('registration_fee_payment_date')->nullable();
            
            // Admission Confirmation
            $table->boolean('admission_confirmed')->default(false);
            $table->date('admission_confirmed_date')->nullable();
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('set null');
            
            // Additional Information
            $table->text('remarks')->nullable();
            $table->json('documents')->nullable();
            
            // Audit Fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['branch_id', 'application_status']);
            $table->index(['academic_year', 'applying_for_grade']);
            $table->index('application_date');
            $table->index('application_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
