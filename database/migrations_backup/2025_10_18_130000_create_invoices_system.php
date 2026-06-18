<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create advanced invoices system linked to transactions
     */
    public function up(): void
    {
        // Main invoices table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            
            // Link to student or custom customer
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('set null');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('customer_address')->nullable();
            
            // Invoice details
            $table->date('invoice_date');
            $table->date('due_date');
            $table->enum('invoice_type', [
                'Fee Payment', 'Tuition Fee', 'Admission Fee', 'Exam Fee', 
                'Transport Fee', 'Library Fee', 'Hostel Fee', 'Other'
            ])->default('Fee Payment');
            
            // Amounts
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2);
            
            // Status tracking
            $table->enum('status', ['Draft', 'Sent', 'Paid', 'Partial', 'Overdue', 'Cancelled'])->default('Draft');
            $table->enum('payment_status', ['Unpaid', 'Partial', 'Paid'])->default('Unpaid');
            
            // Payment information
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->date('payment_date')->nullable();
            
            // Additional info
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('academic_year', 20);
            
            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['invoice_number', 'status']);
            $table->index(['student_id', 'invoice_date']);
            $table->index(['due_date', 'payment_status']);
            $table->index(['branch_id', 'created_at']);
        });

        // Invoice items (line items)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->string('item_type')->default('Service'); // Service, Product, Fee
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2); // Final amount after tax & discount
            $table->timestamps();
            
            $table->index('transaction_id');
        });

        // Invoice-Transaction mapping (for invoices generated from multiple transactions)
        Schema::create('invoice_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->decimal('amount', 10, 2); // Portion of transaction in this invoice
            $table->timestamps();
            
            $table->unique(['invoice_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_transaction');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};

