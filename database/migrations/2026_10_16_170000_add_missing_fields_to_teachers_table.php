<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add missing comprehensive fields to teachers table to mirror the students table pattern.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Personal & Identity Information
            if (! Schema::hasColumn('teachers', 'middle_name')) {
                $table->string('middle_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'preferred_name')) {
                $table->string('preferred_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'title')) {
                $table->string('title', 20)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'suffix')) {
                $table->string('suffix', 20)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'alternate_email')) {
                $table->string('alternate_email', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'alternate_phone')) {
                $table->string('alternate_phone', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'whatsapp_number')) {
                $table->string('whatsapp_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'landline_number')) {
                $table->string('landline_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'place_of_birth')) {
                $table->string('place_of_birth', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'aadhaar_number')) {
                $table->string('aadhaar_number', 20)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'passport_number')) {
                $table->string('passport_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'passport_expiry')) {
                $table->date('passport_expiry')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'driving_license_number')) {
                $table->string('driving_license_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'driving_license_expiry')) {
                $table->date('driving_license_expiry')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'voter_id')) {
                $table->string('voter_id', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'caste')) {
                $table->string('caste', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'sub_caste')) {
                $table->string('sub_caste', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'mother_tongue')) {
                $table->string('mother_tongue', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'languages_known')) {
                $table->json('languages_known')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'handicap_status')) {
                $table->string('handicap_status', 50)->default('None')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'handicap_details')) {
                $table->text('handicap_details')->nullable();
            }

            // Family Information
            if (! Schema::hasColumn('teachers', 'father_name')) {
                $table->string('father_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'mother_name')) {
                $table->string('mother_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'spouse_name')) {
                $table->string('spouse_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'spouse_date_of_birth')) {
                $table->date('spouse_date_of_birth')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'spouse_occupation')) {
                $table->string('spouse_occupation', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'spouse_phone')) {
                $table->string('spouse_phone', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'spouse_email')) {
                $table->string('spouse_email', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'number_of_children')) {
                $table->integer('number_of_children')->default(0)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'children_details')) {
                $table->json('children_details')->nullable();
            }

            // Detailed Address Information
            if (! Schema::hasColumn('teachers', 'current_city')) {
                $table->string('current_city', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'current_state')) {
                $table->string('current_state', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'current_pincode')) {
                $table->string('current_pincode', 20)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'current_country')) {
                $table->string('current_country', 100)->default('India')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'permanent_city')) {
                $table->string('permanent_city', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'permanent_state')) {
                $table->string('permanent_state', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'permanent_pincode')) {
                $table->string('permanent_pincode', 20)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'permanent_country')) {
                $table->string('permanent_country', 100)->default('India')->nullable();
            }

            // Employment & Professional Details
            if (! Schema::hasColumn('teachers', 'employee_type_detail')) {
                $table->string('employee_type_detail', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'employment_status')) {
                $table->string('employment_status', 50)->default('Active')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'probation_end_date')) {
                $table->date('probation_end_date')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'confirmation_date')) {
                $table->date('confirmation_date')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'reporting_manager')) {
                $table->string('reporting_manager', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'subordinates')) {
                $table->json('subordinates')->nullable();
            }

            // Education & Credentials
            if (! Schema::hasColumn('teachers', 'educational_qualifications')) {
                $table->json('educational_qualifications')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'professional_certifications')) {
                $table->json('professional_certifications')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'training_programs')) {
                $table->json('training_programs')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'awards_recognitions')) {
                $table->json('awards_recognitions')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'publications')) {
                $table->json('publications')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'research_projects')) {
                $table->json('research_projects')->nullable();
            }

            // Skills & Methodologies
            if (! Schema::hasColumn('teachers', 'teaching_experience_years')) {
                $table->decimal('teaching_experience_years', 5, 2)->default(0)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'industry_experience_years')) {
                $table->decimal('industry_experience_years', 5, 2)->default(0)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'technical_skills')) {
                $table->json('technical_skills')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'soft_skills')) {
                $table->json('soft_skills')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'subject_expertise')) {
                $table->json('subject_expertise')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'teaching_methodologies')) {
                $table->json('teaching_methodologies')->nullable();
            }

            // Health & Medical
            if (! Schema::hasColumn('teachers', 'medical_history')) {
                $table->text('medical_history')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'allergies')) {
                $table->text('allergies')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'current_medications')) {
                $table->text('current_medications')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'family_doctor_name')) {
                $table->string('family_doctor_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'family_doctor_phone')) {
                $table->string('family_doctor_phone', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'family_doctor_address')) {
                $table->text('family_doctor_address')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'last_medical_checkup')) {
                $table->date('last_medical_checkup')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'medical_insurance_details')) {
                $table->text('medical_insurance_details')->nullable();
            }

            // Emergency Contacts
            if (! Schema::hasColumn('teachers', 'emergency_contact_number')) {
                $table->string('emergency_contact_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'emergency_contact_2_name')) {
                $table->string('emergency_contact_2_name', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'emergency_contact_2_phone')) {
                $table->string('emergency_contact_2_phone', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'emergency_contact_2_relation')) {
                $table->string('emergency_contact_2_relation', 100)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'emergency_contact_2_address')) {
                $table->text('emergency_contact_2_address')->nullable();
            }

            // Financial & Payroll Details
            if (! Schema::hasColumn('teachers', 'epf_number')) {
                $table->string('epf_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'pf_number')) {
                $table->string('pf_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'esi_number')) {
                $table->string('esi_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'uan_number')) {
                $table->string('uan_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'gratuity_number')) {
                $table->string('gratuity_number', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'ctc')) {
                $table->decimal('ctc', 12, 2)->default(0)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'salary_components')) {
                $table->json('salary_components')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'deductions')) {
                $table->json('deductions')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'income_tax_pan')) {
                $table->string('income_tax_pan', 50)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'account_title')) {
                $table->string('account_title', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'bank_branch_name')) {
                $table->string('bank_branch_name', 255)->nullable();
            }

            // Professional License & Performance
            if (! Schema::hasColumn('teachers', 'previous_employers')) {
                $table->json('previous_employers')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'references')) {
                $table->json('references')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'professional_memberships')) {
                $table->text('professional_memberships')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'professional_license')) {
                $table->string('professional_license', 255)->nullable();
            }
            if (! Schema::hasColumn('teachers', 'performance_reviews')) {
                $table->json('performance_reviews')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'appraisals')) {
                $table->json('appraisals')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'goals_objectives')) {
                $table->json('goals_objectives')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'training_needs')) {
                $table->json('training_needs')->nullable();
            }

            // Notes & Profile Picture
            if (! Schema::hasColumn('teachers', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'hobbies_interests')) {
                $table->text('hobbies_interests')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'volunteer_work')) {
                $table->text('volunteer_work')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'community_involvement')) {
                $table->text('community_involvement')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'personal_statement')) {
                $table->text('personal_statement')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'career_objectives')) {
                $table->text('career_objectives')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'additional_notes')) {
                $table->text('additional_notes')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'profile_picture')) {
                $table->text('profile_picture')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe addition of nullable columns; no destructive rollback needed
    }
};
