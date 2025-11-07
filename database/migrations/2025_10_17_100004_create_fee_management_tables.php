<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Complete fee management system
     */
    public function up(): void
    {
        // FEE TYPES TABLE
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name'); // Tuition, Transport, Exam, etc.
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['branch_id', 'is_active']);
            $table->index('code');
        });

        // FEE STRUCTURES TABLE
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('grade'); // Frontend sends 'grade' not 'grade_level'
            $table->string('fee_type'); // VARCHAR for flexibility
            $table->decimal('amount', 10, 2); // Main amount field
            $table->string('academic_year');
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_period')->nullable(); // Monthly, Quarterly, Annually
            $table->boolean('is_active')->default(true);
            
            // Optional: Breakdown fields (if frontend sends them)
            $table->decimal('tuition_fee', 10, 2)->default(0);
            $table->decimal('admission_fee', 10, 2)->default(0);
            $table->decimal('exam_fee', 10, 2)->default(0);
            $table->decimal('library_fee', 10, 2)->default(0);
            $table->decimal('transport_fee', 10, 2)->default(0);
            $table->decimal('sports_fee', 10, 2)->default(0);
            $table->decimal('lab_fee', 10, 2)->default(0);
            $table->json('other_fees')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            
            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'grade', 'academic_year']);
            $table->index('is_active');
            $table->index('fee_type');
        });

        // FEE INSTALLMENTS TABLE
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->uuid('fee_structure_id');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('cascade');
            $table->integer('installment_number');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->decimal('late_fee_amount', 10, 2)->default(0);
            $table->timestamps();
            
            $table->index(['fee_structure_id', 'due_date']);
        });

        // FEE DISCOUNTS TABLE
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('discount_name');
            $table->enum('discount_type', ['Percentage', 'FixedAmount', 'FullWaiver']);
            $table->decimal('discount_value', 10, 2);
            $table->text('reason');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index(['valid_from', 'valid_to']);
        });

        // STUDENT FEES TABLE - Links students to fee structures
        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->uuid('fee_structure_id');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('cascade');
            $table->string('academic_year');
            $table->decimal('original_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('payable_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2);
            $table->enum('payment_status', ['Unpaid', 'PartiallyPaid', 'Paid', 'Overdue'])->default('Unpaid');
            $table->date('due_date');
            $table->timestamps();
            
            $table->index(['student_id', 'academic_year']);
            $table->index('payment_status');
        });

        // FEE PAYMENTS TABLE
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->uuid('fee_structure_id');
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->string('grade_level');
            $table->string('section')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->date('payment_date')->nullable();
            $table->date('due_date');
            $table->enum('status', ['Paid', 'Pending', 'Overdue', 'Cancelled', 'Partial'])->default('Pending');
            $table->enum('payment_status', ['Completed', 'Pending', 'Partial', 'Failed', 'Refunded'])->default('Pending');
            $table->enum('payment_method', ['Cash', 'Card', 'Bank Transfer', 'Cheque', 'Online'])->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('academic_year');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('invoice_number');
            $table->index(['student_id', 'academic_year']);
            $table->index(['branch_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_installments');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};

