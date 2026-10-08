<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TRANSPORT EXPENSES
        if (! Schema::hasTable('transport_expenses')) {
            Schema::create('transport_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $table->date('expense_date');
                $table->enum('category', ['Repair', 'Tyre', 'Battery', 'Cleaning', 'Permit', 'Insurance', 'Other'])->default('Other');
                $table->string('description');
                $table->decimal('amount', 10, 2);
                $table->string('paid_by')->nullable();
                $table->string('reference_no')->nullable();
                $table->string('attachment_url')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['branch_id', 'expense_date']);
                $table->index(['vehicle_id', 'category']);
            });
        }

        // 2. FUEL ENTRIES WITH MILEAGE TRACKING
        if (! Schema::hasTable('transport_fuel_entries')) {
            Schema::create('transport_fuel_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
                $table->date('entry_date');
                $table->enum('fuel_type', ['Diesel', 'Petrol', 'CNG', 'Electric'])->default('Diesel');
                $table->decimal('quantity_litres', 8, 2);
                $table->decimal('rate_per_litre', 8, 2);
                $table->decimal('total_amount', 10, 2);
                $table->integer('odometer_reading');
                $table->decimal('mileage_calculated', 6, 2)->nullable(); // km/l calculated between sequential odometer readings
                $table->string('invoice_no')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['branch_id', 'entry_date']);
                $table->index(['vehicle_id', 'entry_date']);
            });
        }

        // 3. VEHICLE MAINTENANCE LOGS
        if (! Schema::hasTable('transport_maintenance_logs')) {
            Schema::create('transport_maintenance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
                $table->date('service_date');
                $table->enum('service_type', ['Regular Service', 'Tyre Replacement', 'Major Overhaul', 'Oil Change', 'Inspection', 'Battery Replacement', 'Other'])->default('Regular Service');
                $table->text('description')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('garage_name')->nullable();
                $table->date('next_service_date')->nullable();
                $table->integer('next_service_km')->nullable();
                $table->enum('status', ['Scheduled', 'Completed'])->default('Completed');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['branch_id', 'service_date']);
                $table->index(['vehicle_id', 'service_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_maintenance_logs');
        Schema::dropIfExists('transport_fuel_entries');
        Schema::dropIfExists('transport_expenses');
    }
};
