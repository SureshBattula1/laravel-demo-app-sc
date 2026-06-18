<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create comprehensive accounting system
     */
    public function up(): void
    {
        // Account Categories Table
        if (!Schema::hasTable('account_categories')) {
            Schema::create('account_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->enum('type', ['Income', 'Expense'])->comment('Income or Expense');
                $table->enum('sub_type', [
                    // Income types
                    'Fee', 'Donation', 'Grant', 'Other Income',
                    // Expense types
                    'Salary', 'Maintenance', 'Utilities', 'Supplies', 
                    'Transport', 'Food', 'Tips', 'Other Expense'
                ])->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['type', 'is_active']);
            });
        }

        // Transactions Table - Main accounting table
        if (!Schema::hasTable('transactions')) {
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
                $table->string('party_name')->nullable()->comment('Person/Organization');
                $table->string('party_type')->nullable()->comment('Student, Teacher, Vendor, etc.');
                $table->unsignedBigInteger('party_id')->nullable()->comment('Related record ID');
                
                // Payment Details
                $table->enum('payment_method', ['Cash', 'Check', 'Card', 'Bank Transfer', 'UPI', 'Other'])->default('Cash');
                $table->string('payment_reference')->nullable()->comment('Check number, transaction ID, etc.');
                $table->string('bank_name')->nullable();
                
                // Additional Information
                $table->text('description');
                $table->text('notes')->nullable();
                $table->json('attachments')->nullable()->comment('Receipt/invoice files');
                
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
        }

        // Salary Payments Table (Specific expense type)
        if (!Schema::hasTable('salary_payments')) {
            Schema::create('salary_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
                $table->foreignId('employee_id')->constrained('users')->onDelete('restrict');
                $table->enum('employee_type', ['Teacher', 'Staff'])->default('Teacher');
                
                // Salary Breakdown
                $table->decimal('basic_salary', 10, 2);
                $table->decimal('allowances', 10, 2)->default(0)->comment('HRA, DA, etc.');
                $table->decimal('deductions', 10, 2)->default(0)->comment('Tax, PF, etc.');
                $table->decimal('net_salary', 10, 2);
                
                // Period
                $table->string('salary_month', 20);
                $table->string('salary_year', 10);
                
                $table->text('remarks')->nullable();
                $table->timestamps();
                
                $table->index(['employee_id', 'salary_year', 'salary_month']);
            });
        }

        // Budgets Table (for planning)
        if (!Schema::hasTable('budgets')) {
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
        }

        // Seed default categories
        $this->seedAccountCategories();
    }

    /**
     * Seed default account categories
     */
    private function seedAccountCategories(): void
    {
        $categories = [
            // Income Categories
            ['name' => 'Student Fees', 'code' => 'INC-FEE', 'type' => 'Income', 'sub_type' => 'Fee'],
            ['name' => 'Admission Fees', 'code' => 'INC-ADM', 'type' => 'Income', 'sub_type' => 'Fee'],
            ['name' => 'Exam Fees', 'code' => 'INC-EXM', 'type' => 'Income', 'sub_type' => 'Fee'],
            ['name' => 'Donations', 'code' => 'INC-DON', 'type' => 'Income', 'sub_type' => 'Donation'],
            ['name' => 'Grants', 'code' => 'INC-GRN', 'type' => 'Income', 'sub_type' => 'Grant'],
            ['name' => 'Other Income', 'code' => 'INC-OTH', 'type' => 'Income', 'sub_type' => 'Other Income'],
            
            // Expense Categories
            ['name' => 'Teacher Salaries', 'code' => 'EXP-SAL-TCH', 'type' => 'Expense', 'sub_type' => 'Salary'],
            ['name' => 'Staff Salaries', 'code' => 'EXP-SAL-STF', 'type' => 'Expense', 'sub_type' => 'Salary'],
            ['name' => 'Building Maintenance', 'code' => 'EXP-MNT-BLD', 'type' => 'Expense', 'sub_type' => 'Maintenance'],
            ['name' => 'Equipment Maintenance', 'code' => 'EXP-MNT-EQP', 'type' => 'Expense', 'sub_type' => 'Maintenance'],
            ['name' => 'Electricity', 'code' => 'EXP-UTL-ELC', 'type' => 'Expense', 'sub_type' => 'Utilities'],
            ['name' => 'Water', 'code' => 'EXP-UTL-WTR', 'type' => 'Expense', 'sub_type' => 'Utilities'],
            ['name' => 'Internet', 'code' => 'EXP-UTL-NET', 'type' => 'Expense', 'sub_type' => 'Utilities'],
            ['name' => 'Stationery', 'code' => 'EXP-SUP-STA', 'type' => 'Expense', 'sub_type' => 'Supplies'],
            ['name' => 'Books & Materials', 'code' => 'EXP-SUP-BKS', 'type' => 'Expense', 'sub_type' => 'Supplies'],
            ['name' => 'Transport', 'code' => 'EXP-TRP', 'type' => 'Expense', 'sub_type' => 'Transport'],
            ['name' => 'Food & Canteen', 'code' => 'EXP-FOD', 'type' => 'Expense', 'sub_type' => 'Food'],
            ['name' => 'Tips & Gratuity', 'code' => 'EXP-TIP', 'type' => 'Expense', 'sub_type' => 'Tips'],
            ['name' => 'Other Expenses', 'code' => 'EXP-OTH', 'type' => 'Expense', 'sub_type' => 'Other Expense'],
        ];

        foreach ($categories as $category) {
            DB::table('account_categories')->insert([
                'name' => $category['name'],
                'code' => $category['code'],
                'type' => $category['type'],
                'sub_type' => $category['sub_type'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('account_categories');
    }
};

