<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fee Types
        if (!Schema::hasTable('fee_types')) {
            Schema::create('fee_types', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Tuition, Transport, Exam, etc.
                $table->string('code')->unique();
                $table->boolean('is_mandatory')->default(true);
                $table->boolean('is_refundable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Fee Installments
        if (!Schema::hasTable('fee_installments')) {
            Schema::create('fee_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_structure_id')->constrained('fee_structures')->onDelete('cascade');
                $table->integer('installment_number');
                $table->decimal('amount', 10, 2);
                $table->date('due_date');
                $table->decimal('late_fee_amount', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        // Fee Discounts
        if (!Schema::hasTable('fee_discounts')) {
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
            });
        }

        // Student Fees (Links students to fee structures)
        if (!Schema::hasTable('student_fees')) {
            Schema::create('student_fees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('fee_structure_id')->constrained('fee_structures')->onDelete('cascade');
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
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_installments');
        Schema::dropIfExists('fee_types');
    }
};

