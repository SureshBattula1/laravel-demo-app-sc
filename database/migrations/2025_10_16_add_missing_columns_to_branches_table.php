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
        Schema::table('branches', function (Blueprint $table) {
            // Add parent-child relationship
            if (!Schema::hasColumn('branches', 'parent_branch_id')) {
                $table->unsignedBigInteger('parent_branch_id')->nullable()->after('code');
                $table->foreign('parent_branch_id')->references('id')->on('branches')->onDelete('set null');
            }
            
            // Add branch type
            if (!Schema::hasColumn('branches', 'branch_type')) {
                $table->enum('branch_type', ['HeadOffice', 'RegionalOffice', 'School', 'Campus', 'SubBranch'])
                      ->default('School')->after('parent_branch_id');
            }
            
            // Add location fields
            if (!Schema::hasColumn('branches', 'region')) {
                $table->string('region', 100)->nullable()->after('country');
            }
            if (!Schema::hasColumn('branches', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('pincode');
            }
            if (!Schema::hasColumn('branches', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('branches', 'timezone')) {
                $table->string('timezone', 50)->nullable()->after('longitude');
            }
            
            // Add contact fields
            if (!Schema::hasColumn('branches', 'website')) {
                $table->string('website')->nullable()->after('email');
            }
            if (!Schema::hasColumn('branches', 'fax')) {
                $table->string('fax', 20)->nullable()->after('website');
            }
            if (!Schema::hasColumn('branches', 'emergency_contact')) {
                $table->string('emergency_contact', 20)->nullable()->after('fax');
            }
            
            // Add date fields
            if (!Schema::hasColumn('branches', 'opening_date')) {
                $table->date('opening_date')->nullable()->after('established_date');
            }
            if (!Schema::hasColumn('branches', 'closing_date')) {
                $table->date('closing_date')->nullable()->after('opening_date');
            }
            
            // Add academic fields
            if (!Schema::hasColumn('branches', 'board')) {
                $table->string('board', 100)->nullable()->after('affiliation_number');
            }
            if (!Schema::hasColumn('branches', 'accreditations')) {
                $table->json('accreditations')->nullable()->after('board');
            }
            if (!Schema::hasColumn('branches', 'grades_offered')) {
                $table->json('grades_offered')->nullable()->after('accreditations');
            }
            if (!Schema::hasColumn('branches', 'academic_year_start')) {
                $table->string('academic_year_start', 5)->nullable()->after('grades_offered');
            }
            if (!Schema::hasColumn('branches', 'academic_year_end')) {
                $table->string('academic_year_end', 5)->nullable()->after('academic_year_start');
            }
            if (!Schema::hasColumn('branches', 'current_academic_year')) {
                $table->string('current_academic_year', 20)->nullable()->after('academic_year_end');
            }
            
            // Add capacity fields
            if (!Schema::hasColumn('branches', 'total_capacity')) {
                $table->integer('total_capacity')->default(0)->after('current_academic_year');
            }
            if (!Schema::hasColumn('branches', 'current_enrollment')) {
                $table->integer('current_enrollment')->default(0)->after('total_capacity');
            }
            if (!Schema::hasColumn('branches', 'facilities')) {
                $table->json('facilities')->nullable()->after('current_enrollment');
            }
            
            // Add financial fields
            if (!Schema::hasColumn('branches', 'tax_id')) {
                $table->string('tax_id', 50)->nullable()->after('facilities');
            }
            if (!Schema::hasColumn('branches', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('branches', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('branches', 'ifsc_code')) {
                $table->string('ifsc_code', 20)->nullable()->after('bank_account_number');
            }
            
            // Add feature flags
            if (!Schema::hasColumn('branches', 'is_residential')) {
                $table->boolean('is_residential')->default(false)->after('is_main_branch');
            }
            if (!Schema::hasColumn('branches', 'has_hostel')) {
                $table->boolean('has_hostel')->default(false)->after('is_residential');
            }
            if (!Schema::hasColumn('branches', 'has_transport')) {
                $table->boolean('has_transport')->default(false)->after('has_hostel');
            }
            if (!Schema::hasColumn('branches', 'has_library')) {
                $table->boolean('has_library')->default(false)->after('has_transport');
            }
            if (!Schema::hasColumn('branches', 'has_lab')) {
                $table->boolean('has_lab')->default(false)->after('has_library');
            }
            if (!Schema::hasColumn('branches', 'has_canteen')) {
                $table->boolean('has_canteen')->default(false)->after('has_lab');
            }
            if (!Schema::hasColumn('branches', 'has_sports')) {
                $table->boolean('has_sports')->default(false)->after('has_canteen');
            }
            
            // Add status field
            if (!Schema::hasColumn('branches', 'status')) {
                $table->enum('status', ['Active', 'Inactive', 'UnderConstruction', 'Maintenance', 'Closed'])
                      ->default('Active')->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['parent_branch_id']);
            
            // Drop columns
            $columns = [
                'parent_branch_id', 'branch_type', 'region', 'latitude', 'longitude', 'timezone',
                'website', 'fax', 'emergency_contact', 'opening_date', 'closing_date',
                'board', 'accreditations', 'grades_offered', 'academic_year_start', 'academic_year_end',
                'current_academic_year', 'total_capacity', 'current_enrollment', 'facilities',
                'tax_id', 'bank_name', 'bank_account_number', 'ifsc_code',
                'is_residential', 'has_hostel', 'has_transport', 'has_library', 'has_lab',
                'has_canteen', 'has_sports', 'status'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

