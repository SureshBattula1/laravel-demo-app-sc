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
            // Add after transfer_certificate_number (which exists in the table)
            if (!Schema::hasColumn('students', 'voter_id')) {
                $table->string('voter_id', 20)->nullable()->after('transfer_certificate_number');
            }
            if (!Schema::hasColumn('students', 'ration_card_number')) {
                $table->string('ration_card_number', 30)->nullable()->after('voter_id');
            }
            if (!Schema::hasColumn('students', 'domicile_certificate_number')) {
                $table->string('domicile_certificate_number', 50)->nullable()->after('ration_card_number');
            }
            if (!Schema::hasColumn('students', 'income_certificate_number')) {
                $table->string('income_certificate_number', 50)->nullable()->after('domicile_certificate_number');
            }
            if (!Schema::hasColumn('students', 'caste_certificate_number')) {
                $table->string('caste_certificate_number', 50)->nullable()->after('income_certificate_number');
            }
            
            // Enhanced Previous Education (Missing fields)
            if (!Schema::hasColumn('students', 'tc_number')) {
                $table->string('tc_number', 50)->nullable()->after('transfer_certificate_number');
            }
            if (!Schema::hasColumn('students', 'tc_date')) {
                $table->date('tc_date')->nullable()->after('tc_number');
            }
            if (!Schema::hasColumn('students', 'previous_student_id')) {
                $table->string('previous_student_id', 50)->nullable()->after('tc_date');
            }
            if (!Schema::hasColumn('students', 'medium_of_instruction')) {
                $table->string('medium_of_instruction', 50)->default('English')->after('previous_student_id');
            }
            if (!Schema::hasColumn('students', 'language_preferences')) {
                $table->json('language_preferences')->nullable()->after('medium_of_instruction');
            }
            
            // Enhanced Health & Medical (Missing fields)
            if (!Schema::hasColumn('students', 'vision_status')) {
                $table->string('vision_status', 50)->nullable()->after('weight_kg');
            }
            if (!Schema::hasColumn('students', 'hearing_status')) {
                $table->string('hearing_status', 50)->nullable()->after('vision_status');
            }
            if (!Schema::hasColumn('students', 'chronic_conditions')) {
                $table->text('chronic_conditions')->nullable()->after('hearing_status');
            }
            if (!Schema::hasColumn('students', 'current_medications')) {
                $table->text('current_medications')->nullable()->after('chronic_conditions');
            }
            if (!Schema::hasColumn('students', 'medical_insurance')) {
                $table->boolean('medical_insurance')->default(false)->after('current_medications');
            }
            if (!Schema::hasColumn('students', 'insurance_provider')) {
                $table->string('insurance_provider', 100)->nullable()->after('medical_insurance');
            }
            if (!Schema::hasColumn('students', 'insurance_policy_number')) {
                $table->string('insurance_policy_number', 50)->nullable()->after('insurance_provider');
            }
            if (!Schema::hasColumn('students', 'last_health_checkup')) {
                $table->date('last_health_checkup')->nullable()->after('insurance_policy_number');
            }
            if (!Schema::hasColumn('students', 'family_doctor_name')) {
                $table->string('family_doctor_name', 100)->nullable()->after('last_health_checkup');
            }
            if (!Schema::hasColumn('students', 'family_doctor_phone')) {
                $table->string('family_doctor_phone', 20)->nullable()->after('family_doctor_name');
            }
            if (!Schema::hasColumn('students', 'vaccination_status')) {
                $table->string('vaccination_status', 50)->default('Complete')->after('family_doctor_phone');
            }
            if (!Schema::hasColumn('students', 'vaccination_records')) {
                $table->json('vaccination_records')->nullable()->after('vaccination_status');
            }
            if (!Schema::hasColumn('students', 'special_needs')) {
                $table->boolean('special_needs')->default(false)->after('vaccination_records');
            }
            if (!Schema::hasColumn('students', 'special_needs_details')) {
                $table->text('special_needs_details')->nullable()->after('special_needs');
            }
            
            // Additional Student Information (Missing fields)
            if (!Schema::hasColumn('students', 'hobbies_interests')) {
                $table->json('hobbies_interests')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('students', 'extra_curricular_activities')) {
                $table->json('extra_curricular_activities')->nullable()->after('hobbies_interests');
            }
            if (!Schema::hasColumn('students', 'achievements')) {
                $table->json('achievements')->nullable()->after('extra_curricular_activities');
            }
            if (!Schema::hasColumn('students', 'sports_participation')) {
                $table->json('sports_participation')->nullable()->after('achievements');
            }
            if (!Schema::hasColumn('students', 'cultural_activities')) {
                $table->json('cultural_activities')->nullable()->after('sports_participation');
            }
            if (!Schema::hasColumn('students', 'behavior_records')) {
                $table->text('behavior_records')->nullable()->after('cultural_activities');
            }
            if (!Schema::hasColumn('students', 'counselor_notes')) {
                $table->text('counselor_notes')->nullable()->after('behavior_records');
            }
            if (!Schema::hasColumn('students', 'special_instructions')) {
                $table->text('special_instructions')->nullable()->after('counselor_notes');
            }
            
            // Hostel Information (Missing fields) - Add after remarks instead of transport_fee
            if (!Schema::hasColumn('students', 'hostel_required')) {
                $table->boolean('hostel_required')->default(false)->after('special_instructions');
            }
            if (!Schema::hasColumn('students', 'hostel_name')) {
                $table->string('hostel_name', 100)->nullable()->after('hostel_required');
            }
            if (!Schema::hasColumn('students', 'hostel_room_number')) {
                $table->string('hostel_room_number', 20)->nullable()->after('hostel_name');
            }
            if (!Schema::hasColumn('students', 'hostel_fee')) {
                $table->decimal('hostel_fee', 8, 2)->nullable()->after('hostel_room_number');
            }
            
            // Library Information (Missing fields) - Add after profile_picture instead of student_id_card_number
            if (!Schema::hasColumn('students', 'library_card_number')) {
                $table->string('library_card_number', 50)->nullable()->after('profile_picture');
            }
            if (!Schema::hasColumn('students', 'library_card_issue_date')) {
                $table->date('library_card_issue_date')->nullable()->after('library_card_number');
            }
            if (!Schema::hasColumn('students', 'library_card_expiry_date')) {
                $table->date('library_card_expiry_date')->nullable()->after('library_card_issue_date');
            }
            
            // Admission related (Missing fields)
            if (!Schema::hasColumn('students', 'admission_type')) {
                $table->enum('admission_type', ['Regular', 'Transfer', 'Readmission'])->default('Regular')->after('admission_date');
            }
            if (!Schema::hasColumn('students', 'leaving_date')) {
                $table->date('leaving_date')->nullable()->after('admission_type');
            }
            if (!Schema::hasColumn('students', 'leaving_reason')) {
                $table->string('leaving_reason', 255)->nullable()->after('leaving_date');
            }
            if (!Schema::hasColumn('students', 'tc_issued_number')) {
                $table->string('tc_issued_number', 50)->nullable()->after('leaving_reason');
            }
            
            // Add indexes for frequently queried fields (only if columns exist)
            if (Schema::hasColumn('students', 'voter_id')) {
                try {
                    $table->index('voter_id');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
            if (Schema::hasColumn('students', 'ration_card_number')) {
                try {
                    $table->index('ration_card_number');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
            if (Schema::hasColumn('students', 'library_card_number')) {
                try {
                    $table->index('library_card_number');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
            // Only add transport index if columns exist
            if (Schema::hasColumn('students', 'transport_required') && Schema::hasColumn('students', 'transport_route')) {
                try {
                    $table->index(['transport_required', 'transport_route']);
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
            if (Schema::hasColumn('students', 'hostel_required') && Schema::hasColumn('students', 'hostel_name')) {
                try {
                    $table->index(['hostel_required', 'hostel_name']);
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop indexes first (only if they exist)
            if (Schema::hasColumn('students', 'transport_required') && Schema::hasColumn('students', 'transport_route')) {
                try {
                    $table->dropIndex(['transport_required', 'transport_route']);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
            }
            try {
                $table->dropIndex(['hostel_required', 'hostel_name']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            try {
                $table->dropIndex(['voter_id']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            try {
                $table->dropIndex(['ration_card_number']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            try {
                $table->dropIndex(['library_card_number']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
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

