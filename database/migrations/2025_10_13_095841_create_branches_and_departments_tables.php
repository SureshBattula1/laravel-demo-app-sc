<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create branches and departments tables
     */
    public function up(): void
    {
        // BRANCHES TABLE - Complete with all fields
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedBigInteger('parent_branch_id')->nullable();
            $table->enum('branch_type', ['HeadOffice', 'RegionalOffice', 'School', 'Campus', 'SubBranch'])->default('School');
            
            // Address Information
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('country');
            $table->string('region', 100)->nullable();
            $table->string('pincode');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50)->nullable();
            
            // Contact Information
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('website')->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('emergency_contact', 20)->nullable();
            
            // Administration
            $table->string('principal_name')->nullable();
            $table->string('principal_contact')->nullable();
            $table->string('principal_email')->nullable();
            
            // Dates
            $table->date('established_date')->nullable();
            $table->date('opening_date')->nullable();
            $table->date('closing_date')->nullable();
            
            // Academic Information
            $table->string('affiliation_number')->nullable();
            $table->string('board', 100)->nullable();
            $table->json('accreditations')->nullable();
            $table->json('grades_offered')->nullable();
            $table->string('academic_year_start', 5)->nullable();
            $table->string('academic_year_end', 5)->nullable();
            $table->string('current_academic_year', 20)->nullable();
            
            // Capacity & Enrollment
            $table->integer('total_capacity')->default(0);
            $table->integer('current_enrollment')->default(0);
            
            // Facilities
            $table->json('facilities')->nullable();
            $table->boolean('is_residential')->default(false);
            $table->boolean('has_hostel')->default(false);
            $table->boolean('has_transport')->default(false);
            $table->boolean('has_library')->default(false);
            $table->boolean('has_lab')->default(false);
            $table->boolean('has_canteen')->default(false);
            $table->boolean('has_sports')->default(false);
            
            // Financial Information
            $table->string('tax_id', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            
            // Status & Settings
            $table->boolean('is_main_branch')->default(false);
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['Active', 'Inactive', 'UnderConstruction', 'Maintenance', 'Closed'])->default('Active');
            $table->string('logo')->nullable();
            $table->json('settings')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key
            $table->foreign('parent_branch_id')->references('id')->on('branches')->onDelete('set null');
            
            // Indexes
            $table->index('code');
            $table->index('branch_type');
            $table->index('is_active');
            $table->index('status');
        });

        // DEPARTMENTS TABLE - Complete with all fields
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('head');
            $table->foreignId('head_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('description')->nullable();
            $table->date('established_date')->nullable();
            $table->integer('students_count')->default(0);
            $table->integer('teachers_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_active']);
            $table->index('head_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
    }
};

