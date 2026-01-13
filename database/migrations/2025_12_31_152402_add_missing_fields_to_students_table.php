<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add missing fields that are in the Student model but not in the database
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Personal Information - Caste fields
            if (!Schema::hasColumn('students', 'caste')) {
                $table->string('caste', 50)->nullable()->after('category');
            }
            if (!Schema::hasColumn('students', 'sub_caste')) {
                $table->string('sub_caste', 50)->nullable()->after('caste');
            }
            
            // Enhanced Address fields
            if (!Schema::hasColumn('students', 'current_district')) {
                $table->string('current_district', 100)->nullable()->after('current_address');
            }
            if (!Schema::hasColumn('students', 'current_landmark')) {
                $table->string('current_landmark', 255)->nullable()->after('current_district');
            }
            if (!Schema::hasColumn('students', 'permanent_district')) {
                $table->string('permanent_district', 100)->nullable()->after('permanent_address');
            }
            if (!Schema::hasColumn('students', 'permanent_landmark')) {
                $table->string('permanent_landmark', 255)->nullable()->after('permanent_district');
            }
            if (!Schema::hasColumn('students', 'correspondence_address')) {
                $table->text('correspondence_address')->nullable()->after('permanent_landmark');
            }
            
            // Identity Documents - Missing ones
            if (!Schema::hasColumn('students', 'aadhaar_number')) {
                $table->string('aadhaar_number', 20)->nullable()->after('sub_caste');
            }
            if (!Schema::hasColumn('students', 'pen_number')) {
                $table->string('pen_number', 50)->nullable()->after('aadhaar_number');
            }
            if (!Schema::hasColumn('students', 'birth_certificate_number')) {
                $table->string('birth_certificate_number', 50)->nullable()->after('pen_number');
            }
            if (!Schema::hasColumn('students', 'passport_number')) {
                $table->string('passport_number', 50)->nullable()->after('birth_certificate_number');
            }
            if (!Schema::hasColumn('students', 'passport_expiry')) {
                $table->date('passport_expiry')->nullable()->after('passport_number');
            }
            if (!Schema::hasColumn('students', 'student_id_card_number')) {
                $table->string('student_id_card_number', 50)->nullable()->after('passport_expiry');
            }
            
            // Sibling Information
            if (!Schema::hasColumn('students', 'number_of_siblings')) {
                $table->integer('number_of_siblings')->default(0)->after('correspondence_address');
            }
            if (!Schema::hasColumn('students', 'sibling_details')) {
                $table->json('sibling_details')->nullable()->after('number_of_siblings');
            }
            if (!Schema::hasColumn('students', 'sibling_discount_applicable')) {
                $table->boolean('sibling_discount_applicable')->default(false)->after('sibling_details');
            }
            if (!Schema::hasColumn('students', 'sibling_discount_percentage')) {
                $table->decimal('sibling_discount_percentage', 5, 2)->default(0)->after('sibling_discount_applicable');
            }
            
            // Enhanced Parent/Guardian Information
            if (!Schema::hasColumn('students', 'father_organization')) {
                $table->string('father_organization', 100)->nullable()->after('father_qualification');
            }
            if (!Schema::hasColumn('students', 'father_designation')) {
                $table->string('father_designation', 100)->nullable()->after('father_organization');
            }
            if (!Schema::hasColumn('students', 'father_aadhaar')) {
                $table->string('father_aadhaar', 20)->nullable()->after('father_annual_income');
            }
            
            if (!Schema::hasColumn('students', 'mother_organization')) {
                $table->string('mother_organization', 100)->nullable()->after('mother_qualification');
            }
            if (!Schema::hasColumn('students', 'mother_designation')) {
                $table->string('mother_designation', 100)->nullable()->after('mother_organization');
            }
            if (!Schema::hasColumn('students', 'mother_aadhaar')) {
                $table->string('mother_aadhaar', 20)->nullable()->after('mother_annual_income');
            }
            
            if (!Schema::hasColumn('students', 'guardian_qualification')) {
                $table->string('guardian_qualification', 100)->nullable()->after('guardian_phone');
            }
            if (!Schema::hasColumn('students', 'guardian_occupation')) {
                $table->string('guardian_occupation', 100)->nullable()->after('guardian_qualification');
            }
            if (!Schema::hasColumn('students', 'guardian_email')) {
                $table->string('guardian_email')->nullable()->after('guardian_occupation');
            }
            if (!Schema::hasColumn('students', 'guardian_address')) {
                $table->text('guardian_address')->nullable()->after('guardian_email');
            }
            if (!Schema::hasColumn('students', 'guardian_annual_income')) {
                $table->decimal('guardian_annual_income', 12, 2)->nullable()->after('guardian_address');
            }
            
            // Transport Details
            if (!Schema::hasColumn('students', 'transport_required')) {
                $table->boolean('transport_required')->default(false)->after('emergency_contact_relation');
            }
            if (!Schema::hasColumn('students', 'transport_route')) {
                $table->string('transport_route', 100)->nullable()->after('transport_required');
            }
            if (!Schema::hasColumn('students', 'pickup_point')) {
                $table->string('pickup_point', 255)->nullable()->after('transport_route');
            }
            if (!Schema::hasColumn('students', 'drop_point')) {
                $table->string('drop_point', 255)->nullable()->after('pickup_point');
            }
            if (!Schema::hasColumn('students', 'vehicle_number')) {
                $table->string('vehicle_number', 50)->nullable()->after('drop_point');
            }
            if (!Schema::hasColumn('students', 'pickup_time')) {
                $table->time('pickup_time')->nullable()->after('vehicle_number');
            }
            if (!Schema::hasColumn('students', 'drop_time')) {
                $table->time('drop_time')->nullable()->after('pickup_time');
            }
            if (!Schema::hasColumn('students', 'transport_fee')) {
                $table->decimal('transport_fee', 8, 2)->nullable()->after('drop_time');
            }
            
            // Previous Education - Enhanced fields
            if (!Schema::hasColumn('students', 'previous_school_board')) {
                $table->string('previous_school_board', 50)->nullable()->after('previous_grade');
            }
            if (!Schema::hasColumn('students', 'previous_school_address')) {
                $table->text('previous_school_address')->nullable()->after('previous_school_board');
            }
            if (!Schema::hasColumn('students', 'previous_school_phone')) {
                $table->string('previous_school_phone', 20)->nullable()->after('previous_school_address');
            }
            
            // Fee & Scholarship
            if (!Schema::hasColumn('students', 'fee_concession_applicable')) {
                $table->boolean('fee_concession_applicable')->default(false)->after('special_needs_details');
            }
            if (!Schema::hasColumn('students', 'concession_type')) {
                $table->string('concession_type', 100)->nullable()->after('fee_concession_applicable');
            }
            if (!Schema::hasColumn('students', 'concession_percentage')) {
                $table->decimal('concession_percentage', 5, 2)->default(0)->after('concession_type');
            }
            if (!Schema::hasColumn('students', 'scholarship_name')) {
                $table->string('scholarship_name', 100)->nullable()->after('concession_percentage');
            }
            if (!Schema::hasColumn('students', 'scholarship_details')) {
                $table->text('scholarship_details')->nullable()->after('scholarship_name');
            }
            if (!Schema::hasColumn('students', 'economic_status')) {
                $table->string('economic_status', 50)->nullable()->after('scholarship_details');
            }
            if (!Schema::hasColumn('students', 'family_annual_income')) {
                $table->decimal('family_annual_income', 12, 2)->nullable()->after('economic_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'caste', 'sub_caste',
                'current_district', 'current_landmark',
                'permanent_district', 'permanent_landmark', 'correspondence_address',
                'aadhaar_number', 'pen_number', 'birth_certificate_number',
                'passport_number', 'passport_expiry', 'student_id_card_number',
                'number_of_siblings', 'sibling_details', 'sibling_discount_applicable', 'sibling_discount_percentage',
                'father_organization', 'father_designation', 'father_aadhaar',
                'mother_organization', 'mother_designation', 'mother_aadhaar',
                'guardian_qualification', 'guardian_occupation', 'guardian_email', 'guardian_address', 'guardian_annual_income',
                'transport_required', 'transport_route', 'pickup_point', 'drop_point', 'vehicle_number', 'pickup_time', 'drop_time', 'transport_fee',
                'previous_school_board', 'previous_school_address', 'previous_school_phone',
                'fee_concession_applicable', 'concession_type', 'concession_percentage',
                'scholarship_name', 'scholarship_details', 'economic_status', 'family_annual_income'
            ]);
        });
    }
};
