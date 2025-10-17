<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Drop and recreate fee_structures table with new schema
        Schema::dropIfExists('fee_installments');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_structures');
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // Create fee_structures with UUID and new schema
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('grade', 50);
            $table->enum('fee_type', ['Tuition', 'Library', 'Laboratory', 'Sports', 'Transport', 'Exam', 'Other']);
            $table->decimal('amount', 10, 2);
            $table->string('academic_year', 20);
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_period', ['Monthly', 'Quarterly', 'Annually'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'grade', 'fee_type']);
            $table->index('academic_year');
        });
        
        // Recreate fee_payments with UUID
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fee_structure_id');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount_paid', 10, 2);
            $table->dateTime('payment_date');
            $table->enum('payment_method', ['Cash', 'Card', 'Online', 'Cheque', 'Other']);
            $table->string('transaction_id')->nullable();
            $table->string('receipt_number')->unique();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_status', ['Pending', 'Completed', 'Failed', 'Refunded'])->default('Completed');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('cascade');
            $table->index(['student_id', 'payment_date']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_structures');
    }
};

