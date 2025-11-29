<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add only the MISSING comprehensive fields to students table
     * Based on real Indian school management system requirements
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Identity Documents (Missing ones)
            $table->string('voter_id', 20)->nullable()->after('aadhaar_number');
            $table->string('ration_card_number', 30)->nullable()->after('pen_number');
            $table->string('domicile_certificate_number', 50)->nullable()->after('ration_card_number');
            $table->string('income_certificate_number', 50)->nullable()->after('domicile_certificate_number');
            $table->string('caste_certificate_number', 50)->nullable()->after('income_certificate_number');
            
            // Enhanced Previous Education (Missing fields)
            $table->string('tc_number', 50)->nullable()->after('transfer_certificate_number');
            $table->date('tc_date')->nullable()->after('tc_number');
            $table->string('previous_student_id', 50)->nullable()->after('tc_date');
            $table->string('medium_of_instruction', 50)->default('English')->after('previous_student_id');
            $table->json('language_preferences')->nullable()->after('medium_of_instruction');
            
            // Enhanced Health & Medical (Missing fields)
            $table->string('vision_status', 50)->nullable()->after('weight_kg');
            $table->string('hearing_status', 50)->nullable()->after('vision_status');
            $table->text('chronic_conditions')->nullable()->after('hearing_status');
            $table->text('current_medications')->nullable()->after('chronic_conditions');
            $table->boolean('medical_insurance')->default(false)->after('current_medications');
            $table->string('insurance_provider', 100)->nullable()->after('medical_insurance');
            $table->string('insurance_policy_number', 50)->nullable()->after('insurance_provider');
            $table->date('last_health_checkup')->nullable()->after('insurance_policy_number');
            $table->string('family_doctor_name', 100)->nullable()->after('last_health_checkup');
            $table->string('family_doctor_phone', 20)->nullable()->after('family_doctor_name');
            $table->string('vaccination_status', 50)->default('Complete')->after('family_doctor_phone');
            $table->json('vaccination_records')->nullable()->after('vaccination_status');
            $table->boolean('special_needs')->default(false)->after('vaccination_records');
            $table->text('special_needs_details')->nullable()->after('special_needs');
            
            // Additional Student Information (Missing fields)
            $table->json('hobbies_interests')->nullable()->after('remarks');
            $table->json('extra_curricular_activities')->nullable()->after('hobbies_interests');
            $table->json('achievements')->nullable()->after('extra_curricular_activities');
            $table->json('sports_participation')->nullable()->after('achievements');
            $table->json('cultural_activities')->nullable()->after('sports_participation');
            $table->text('behavior_records')->nullable()->after('cultural_activities');
            $table->text('counselor_notes')->nullable()->after('behavior_records');
            $table->text('special_instructions')->nullable()->after('counselor_notes');
            
            // Hostel Information (Missing fields)
            $table->boolean('hostel_required')->default(false)->after('transport_fee');
            $table->string('hostel_name', 100)->nullable()->after('hostel_required');
            $table->string('hostel_room_number', 20)->nullable()->after('hostel_name');
            $table->decimal('hostel_fee', 8, 2)->nullable()->after('hostel_room_number');
            
            // Library Information (Missing fields)
            $table->string('library_card_number', 50)->nullable()->after('student_id_card_number');
            $table->date('library_card_issue_date')->nullable()->after('library_card_number');
            $table->date('library_card_expiry_date')->nullable()->after('library_card_issue_date');
            
            // Admission related (Missing fields)
            $table->enum('admission_type', ['Regular', 'Transfer', 'Readmission'])->default('Regular')->after('admission_date');
            $table->date('leaving_date')->nullable()->after('admission_type');
            $table->string('leaving_reason', 255)->nullable()->after('leaving_date');
            $table->string('tc_issued_number', 50)->nullable()->after('leaving_reason');
            
            // Add indexes for frequently queried fields
            $table->index('voter_id');
            $table->index('ration_card_number');
            $table->index('library_card_number');
            $table->index(['transport_required', 'transport_route']);
            $table->index(['hostel_required', 'hostel_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['transport_required', 'transport_route']);
            $table->dropIndex(['hostel_required', 'hostel_name']);
            $table->dropIndex(['voter_id']);
            $table->dropIndex(['ration_card_number']);
            $table->dropIndex(['library_card_number']);
            
            // Drop columns
            $table->dropColumn([
                // Identity Documents
                'voter_id', 'ration_card_number', 'domicile_certificate_number',
                'income_certificate_number', 'caste_certificate_number',
                
                // Previous Education
                'tc_number', 'tc_date', 'previous_student_id',
                'medium_of_instruction', 'language_preferences',
                
                // Health
                'vision_status', 'hearing_status', 'chronic_conditions', 'current_medications',
                'medical_insurance', 'insurance_provider', 'insurance_policy_number',
                'last_health_checkup', 'family_doctor_name', 'family_doctor_phone',
                'vaccination_status', 'vaccination_records', 'special_needs', 'special_needs_details',
                
                // Additional
                'hobbies_interests', 'extra_curricular_activities', 'achievements',
                'sports_participation', 'cultural_activities', 'behavior_records',
                'counselor_notes', 'special_instructions',
                
                // Hostel
                'hostel_required', 'hostel_name', 'hostel_room_number', 'hostel_fee',
                
                // Library
                'library_card_number', 'library_card_issue_date', 'library_card_expiry_date',
                
                // Admission
                'admission_type', 'leaving_date', 'leaving_reason', 'tc_issued_number'
            ]);
        });
    }
};

