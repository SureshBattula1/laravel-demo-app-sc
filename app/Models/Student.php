<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'branch_id',
        'admission_number',
        'admission_date',
        'admission_type',
        'roll_number',
        'registration_number',
        'grade',
        'section',
        'academic_year',
        'stream',
        'elective_subjects',
        'date_of_birth',
        'gender',
        'blood_group',
        'religion',
        'nationality',
        'mother_tongue',
        'category',
        'caste',
        'sub_caste',
        
        // Identity Documents
        'aadhaar_number',
        'pen_number',
        'birth_certificate_number',
        'passport_number',
        'passport_expiry',
        'student_id_card_number',
        'voter_id',
        'ration_card_number',
        'domicile_certificate_number',
        'income_certificate_number',
        'caste_certificate_number',
        
        // Address
        'current_address',
        'current_district',
        'current_landmark',
        'permanent_address',
        'permanent_district',
        'permanent_landmark',
        'correspondence_address',
        'city',
        'state',
        'country',
        'pincode',
        
        // Sibling Information
        'number_of_siblings',
        'sibling_details',
        'sibling_discount_applicable',
        'sibling_discount_percentage',
        
        // Parent/Guardian Information
        'parent_id',
        'father_name',
        'father_phone',
        'father_email',
        'father_occupation',
        'father_qualification',
        'father_organization',
        'father_designation',
        'father_annual_income',
        'father_aadhaar',
        
        'mother_name',
        'mother_phone',
        'mother_email',
        'mother_occupation',
        'mother_qualification',
        'mother_organization',
        'mother_designation',
        'mother_annual_income',
        'mother_aadhaar',
        
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'guardian_qualification',
        'guardian_occupation',
        'guardian_email',
        'guardian_address',
        'guardian_annual_income',
        
        // Emergency Contact
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        
        // Transport Details
        'transport_required',
        'transport_route',
        'pickup_point',
        'drop_point',
        'vehicle_number',
        'pickup_time',
        'drop_time',
        'transport_fee',
        
        // Hostel
        'hostel_required',
        'hostel_name',
        'hostel_room_number',
        'hostel_fee',
        
        // Library
        'library_card_number',
        'library_card_issue_date',
        'library_card_expiry_date',
        
        // Previous Education
        'previous_school',
        'previous_grade',
        'previous_school_board',
        'previous_school_address',
        'previous_school_phone',
        'previous_percentage',
        'transfer_certificate_number',
        'tc_number',
        'tc_date',
        'previous_student_id',
        'medium_of_instruction',
        'language_preferences',
        
        // Medical Information
        'medical_history',
        'allergies',
        'medications',
        'height_cm',
        'weight_kg',
        'vision_status',
        'hearing_status',
        'chronic_conditions',
        'current_medications',
        'medical_insurance',
        'insurance_provider',
        'insurance_policy_number',
        'last_health_checkup',
        'family_doctor_name',
        'family_doctor_phone',
        'vaccination_status',
        'vaccination_records',
        'special_needs',
        'special_needs_details',
        
        // Fee & Scholarship
        'fee_concession_applicable',
        'concession_type',
        'concession_percentage',
        'scholarship_name',
        'scholarship_details',
        'economic_status',
        'family_annual_income',
        
        // Additional Information
        'hobbies_interests',
        'extra_curricular_activities',
        'achievements',
        'sports_participation',
        'cultural_activities',
        'behavior_records',
        'counselor_notes',
        'special_instructions',
        
        // Status & Documents
        'student_status',
        'admission_status',
        'leaving_date',
        'leaving_reason',
        'tc_issued_number',
        'documents',
        'profile_picture',
        'remarks',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'date_of_birth' => 'date',
        'passport_expiry' => 'date',
        'tc_date' => 'date',
        'last_health_checkup' => 'date',
        'library_card_issue_date' => 'date',
        'library_card_expiry_date' => 'date',
        'leaving_date' => 'date',
        'pickup_time' => 'datetime:H:i',
        'drop_time' => 'datetime:H:i',
        
        // JSON fields
        'elective_subjects' => 'array',
        'sibling_details' => 'array',
        'language_preferences' => 'array',
        'vaccination_records' => 'array',
        'hobbies_interests' => 'array',
        'extra_curricular_activities' => 'array',
        'achievements' => 'array',
        'sports_participation' => 'array',
        'cultural_activities' => 'array',
        'documents' => 'array',
        
        // Boolean fields
        'sibling_discount_applicable' => 'boolean',
        'transport_required' => 'boolean',
        'hostel_required' => 'boolean',
        'medical_insurance' => 'boolean',
        'special_needs' => 'boolean',
        'fee_concession_applicable' => 'boolean',
        
        // Decimal fields
        'father_annual_income' => 'decimal:2',
        'mother_annual_income' => 'decimal:2',
        'guardian_annual_income' => 'decimal:2',
        'family_annual_income' => 'decimal:2',
        'previous_percentage' => 'decimal:2',
        'sibling_discount_percentage' => 'decimal:2',
        'concession_percentage' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'transport_fee' => 'decimal:2',
        'hostel_fee' => 'decimal:2',
    ];
    
    /**
     * Get the profile picture URL
     */
    public function getProfilePictureAttribute($value)
    {
        // If no profile picture in student table, check user's avatar
        if (!$value && $this->user) {
            $value = $this->user->avatar;
        }
        
        if (!$value) {
            return null;
        }
        
        // If already a full URL, return as is
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        // Use Laravel's Storage facade to generate the URL
        try {
            if (\Storage::disk('public')->exists($value)) {
                return \Storage::disk('public')->url($value);
            }
        } catch (\Exception $e) {
            // Fallback to direct url construction
        }
        
        // Fallback: construct URL manually
        if (str_starts_with($value, 'storage/')) {
            return url($value);
        }
        
        return url('storage/' . $value);
    }

    /**
     * Get the user that owns the student
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch that owns the student
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
