<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Complete accounting system
     */
    public function up(): void
    {
        // ACCOUNT CATEGORIES TABLE
        // MOVED TO: 2024_01_01_000001_create_account_categories_table.php
        // This table is now created by a separate migration with improved schema
        // (VARCHAR instead of ENUM for sub_type, and includes soft deletes)

        // TRANSACTIONS TABLE - Main accounting table
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('category_id')->constrained('account_categories')->onDelete('restrict');
            
            // Transaction Details
            $table->string('transaction_number')->unique();
            $table->date('transaction_date');
            $table->enum('type', ['Income', 'Expense']);
            $table->decimal('amount', 12, 2);
            
            // Party Information
            $table->string('party_name')->nullable();
            $table->string('party_type')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            
            // Payment Details
            $table->enum('payment_method', ['Cash', 'Check', 'Card', 'Bank Transfer', 'UPI', 'Other'])->default('Cash');
            $table->string('payment_reference')->nullable();
            $table->string('bank_name')->nullable();
            
            // Additional Information
            $table->text('description');
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            
            // Status & Approval
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            // Accounting Period
            $table->string('financial_year', 20);
            $table->string('month', 20);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'transaction_date']);
            $table->index(['type', 'status']);
            $table->index(['category_id', 'financial_year']);
            $table->index('transaction_number');
        });

        // SALARY PAYMENTS TABLE
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('users')->onDelete('restrict');
            $table->enum('employee_type', ['Teacher', 'Staff'])->default('Teacher');
            
            // Salary Breakdown
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('allowances', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2);
            
            // Period
            $table->string('salary_month', 20);
            $table->string('salary_year', 10);
            
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['employee_id', 'salary_year', 'salary_month']);
        });

        // BUDGETS TABLE
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('account_categories')->onDelete('cascade');
            
            $table->string('financial_year', 20);
            $table->decimal('allocated_amount', 12, 2);
            $table->decimal('utilized_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2);
            
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['branch_id', 'financial_year']);
            $table->unique(['branch_id', 'category_id', 'financial_year']);
        });

        // INVOICES TABLE
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            
            // Customer
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
            
            // Status
            $table->enum('status', ['Draft', 'Sent', 'Paid', 'Partial', 'Overdue', 'Cancelled'])->default('Draft');
            $table->enum('payment_status', ['Unpaid', 'Partial', 'Paid'])->default('Unpaid');
            
            // Payment info
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->date('payment_date')->nullable();
            
            // Additional
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('academic_year', 20);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['invoice_number', 'status']);
            $table->index(['student_id', 'invoice_date']);
            $table->index(['due_date', 'payment_status']);
            $table->index(['branch_id', 'created_at']);
        });

        // INVOICE ITEMS TABLE
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->string('item_type')->default('Service');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            
            $table->index('transaction_id');
        });

        // NOTE: account_categories are now seeded by AccountCategorySeeder
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('transactions');
        // account_categories dropped by its own migration
    }
};

