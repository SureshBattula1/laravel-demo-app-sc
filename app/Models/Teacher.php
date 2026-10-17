<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    // Comprehensive database columns matching all form fields
    protected $fillable = [
        'id', 'user_id', 'branch_id', 'school_id', 'department_id', 'reporting_manager_id',
        'employee_id', 'category_type', 'joining_date', 'leaving_date', 'designation', 'employee_type',
        'employee_type_detail', 'employment_status', 'probation_end_date', 'confirmation_date',
        'reporting_manager', 'subordinates',

        // Identity & Personal
        'middle_name', 'preferred_name', 'title', 'suffix',
        'gender', 'date_of_birth', 'place_of_birth', 'blood_group', 'religion', 'nationality',
        'caste', 'sub_caste', 'mother_tongue', 'languages_known', 'handicap_status', 'handicap_details',
        'pan_number', 'aadhar_number', 'aadhaar_number', 'passport_number', 'passport_expiry',
        'driving_license_number', 'driving_license_expiry', 'voter_id',

        // Family
        'father_name', 'mother_name', 'spouse_name', 'spouse_date_of_birth', 'spouse_occupation',
        'spouse_phone', 'spouse_email', 'number_of_children', 'children_details',

        // Contact & Addresses
        'alternate_email', 'alternate_phone', 'whatsapp_number', 'landline_number',
        'current_address', 'current_city', 'current_state', 'current_pincode', 'current_country',
        'permanent_address', 'permanent_city', 'permanent_state', 'permanent_pincode', 'permanent_country',
        'city', 'state', 'pincode',

        // Education & Credentials
        'qualification', 'educational_qualifications', 'professional_certifications',
        'training_programs', 'awards_recognitions', 'publications', 'research_projects',
        'specialization', 'registration_number', 'salary_grade',

        // Skills & Teaching
        'experience_years', 'teaching_experience_years', 'industry_experience_years',
        'technical_skills', 'soft_skills', 'subject_expertise', 'teaching_methodologies',
        'subjects', 'classes_assigned', 'is_class_teacher', 'class_teacher_of_grade', 'class_teacher_of_section',

        // Health & Medical
        'medical_history', 'allergies', 'current_medications', 'family_doctor_name',
        'family_doctor_phone', 'family_doctor_address', 'last_medical_checkup', 'medical_insurance_details',

        // Emergency Contacts
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_number', 'emergency_contact_relation',
        'emergency_contact_2_name', 'emergency_contact_2_phone', 'emergency_contact_2_relation', 'emergency_contact_2_address',

        // Payroll, Financial & Bank
        'epf_number', 'pf_number', 'esi_number', 'uan_number', 'gratuity_number',
        'basic_salary', 'ctc', 'salary_components', 'deductions', 'income_tax_pan',
        'bank_name', 'bank_account_number', 'bank_ifsc_code', 'account_title', 'bank_branch_name',

        // Additional Professional & Performance
        'previous_employers', 'references', 'professional_memberships', 'professional_license',
        'performance_reviews', 'appraisals', 'goals_objectives', 'training_needs',

        // Notes, Status & Documents
        'notes', 'hobbies_interests', 'volunteer_work', 'community_involvement',
        'personal_statement', 'career_objectives', 'additional_notes',
        'teacher_status', 'remarks', 'extended_profile', 'documents', 'profile_picture',
        'created_at', 'updated_at', 'deleted_at',
    ];

    // Virtual attributes to append to JSON output
    protected $appends = ['profile_picture', 'aadhaar_number', 'first_name', 'last_name', 'email', 'phone'];

    protected $extendedFields = [
        'first_name', 'middle_name', 'last_name', 'preferred_name', 'title', 'suffix',
        'passport_number', 'passport_expiry', 'driving_license_number', 'driving_license_expiry',
        'voter_id', 'place_of_birth', 'religion', 'caste', 'sub_caste', 'mother_tongue',
        'languages_known', 'handicap_status', 'handicap_details', 'father_name', 'mother_name', 'spouse_name',
        'spouse_date_of_birth', 'spouse_occupation', 'spouse_phone', 'spouse_email',
        'number_of_children', 'children_details', 'alternate_email', 'alternate_phone', 'whatsapp_number', 'landline_number',
        'current_address', 'current_city', 'current_state', 'current_pincode', 'current_country',
        'permanent_address', 'permanent_city', 'permanent_state', 'permanent_pincode',
        'permanent_country', 'employee_type_detail', 'employment_status', 'probation_end_date',
        'confirmation_date', 'reporting_manager', 'subordinates', 'qualification',
        'educational_qualifications', 'professional_certifications', 'training_programs',
        'awards_recognitions', 'publications', 'research_projects', 'experience_years',
        'teaching_experience_years', 'industry_experience_years', 'technical_skills',
        'soft_skills', 'subject_expertise', 'teaching_methodologies', 'medical_history',
        'allergies', 'current_medications', 'family_doctor_name', 'family_doctor_phone',
        'family_doctor_address', 'last_medical_checkup', 'medical_insurance_details',
        'emergency_contact_number', 'emergency_contact_2_name', 'emergency_contact_2_phone',
        'emergency_contact_2_relation', 'emergency_contact_2_address', 'pf_number',
        'esi_number', 'uan_number', 'gratuity_number', 'ctc', 'salary_components', 'deductions',
        'income_tax_pan', 'account_title', 'bank_branch_name', 'previous_employers',
        'references', 'professional_memberships', 'professional_license',
        'performance_reviews', 'appraisals', 'goals_objectives', 'training_needs',
        'hobbies_interests', 'volunteer_work', 'community_involvement',
        'personal_statement', 'career_objectives', 'notes', 'additional_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_class_teacher' => 'boolean',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'spouse_date_of_birth' => 'date',
            'passport_expiry' => 'date',
            'driving_license_expiry' => 'date',
            'last_medical_checkup' => 'date',
            'basic_salary' => 'decimal:2',
            'ctc' => 'decimal:2',
            'experience_years' => 'decimal:2',
            'teaching_experience_years' => 'decimal:2',
            'industry_experience_years' => 'decimal:2',
            'number_of_children' => 'integer',
            'subjects' => 'array',
            'classes_assigned' => 'array',
            'extended_profile' => 'array',
            'documents' => 'array',
            'languages_known' => 'array',
            'children_details' => 'array',
            'subordinates' => 'array',
            'educational_qualifications' => 'array',
            'professional_certifications' => 'array',
            'training_programs' => 'array',
            'awards_recognitions' => 'array',
            'publications' => 'array',
            'research_projects' => 'array',
            'technical_skills' => 'array',
            'soft_skills' => 'array',
            'subject_expertise' => 'array',
            'teaching_methodologies' => 'array',
            'salary_components' => 'array',
            'deductions' => 'array',
            'previous_employers' => 'array',
            'references' => 'array',
            'performance_reviews' => 'array',
            'appraisals' => 'array',
            'goals_objectives' => 'array',
            'training_needs' => 'array',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function attachments()
    {
        return $this->hasMany(TeacherAttachment::class);
    }

    public function profilePicture()
    {
        return $this->hasOne(TeacherAttachment::class)
            ->where('document_type', 'profile_picture')
            ->where('is_active', true)
            ->latest();
    }

    // Accessors for extended_profile fields
    // These accessors allow accessing extended_profile fields as if they were regular attributes
    public function getNotesAttribute($value)
    {
        return $value ?? $this->extended_profile['notes'] ?? null;
    }

    public function getHobbiesInterestsAttribute($value)
    {
        return $value ?? $this->extended_profile['hobbies_interests'] ?? null;
    }

    public function getCareerObjectivesAttribute($value)
    {
        return $value ?? $this->extended_profile['career_objectives'] ?? null;
    }

    public function getProfessionalMembershipsAttribute($value)
    {
        return $value ?? $this->extended_profile['professional_memberships'] ?? null;
    }

    public function getProfessionalLicenseAttribute($value)
    {
        return $value ?? $this->extended_profile['professional_license'] ?? null;
    }

    // Add accessor for qualification (database column)
    // Note: qualification is stored as TEXT in database (changed from JSON via migration 2025_11_10_115217)
    // This accessor provides backward compatibility with extended_profile
    public function getQualificationAttribute($value)
    {
        // First check actual database column (TEXT type), then extended_profile for backward compatibility
        if (isset($this->attributes['qualification']) && ! empty($this->attributes['qualification'])) {
            // If it's a JSON string, decode it; otherwise return as string
            $decoded = json_decode($this->attributes['qualification'], true);

            return $decoded !== null ? $decoded : $this->attributes['qualification'];
        }

        return $value ?? $this->extended_profile['qualification'] ?? null;
    }

    // Add accessor for experience_years (database column)
    public function getExperienceYearsAttribute($value)
    {
        // First check actual database column, then extended_profile for backward compatibility
        if (isset($this->attributes['experience_years'])) {
            return $this->attributes['experience_years'];
        }

        return $value ?? $this->extended_profile['experience_years'] ?? 0;
    }

    // ✅ Add accessor & mutator for aadhaar_number (keeping both aadhaar_number and legacy aadhar_number in sync)
    public function getAadhaarNumberAttribute($value)
    {
        return $value ?? $this->attributes['aadhaar_number'] ?? $this->attributes['aadhar_number'] ?? null;
    }

    public function setAadhaarNumberAttribute($value)
    {
        $this->attributes['aadhaar_number'] = $value;
        $this->attributes['aadhar_number'] = $value;
    }

    // Accessors for user account fields (for seamless frontend JSON compatibility)
    public function getFirstNameAttribute()
    {
        return $this->user->first_name ?? null;
    }

    public function getLastNameAttribute()
    {
        return $this->user->last_name ?? null;
    }

    public function getEmailAttribute()
    {
        return $this->user->email ?? null;
    }

    public function getPhoneAttribute()
    {
        return $this->user->phone ?? null;
    }

    // Profile picture URL accessor
    public function getProfilePictureAttribute($value = null)
    {
        $val = $value ?? $this->attributes['profile_picture'] ?? null;

        // Extract from extended_profile JSON as fallback
        if (! $val && isset($this->attributes['extended_profile'])) {
            $extendedProfile = is_string($this->attributes['extended_profile'])
                ? json_decode($this->attributes['extended_profile'], true)
                : $this->attributes['extended_profile'];

            $val = $extendedProfile['profile_picture'] ?? null;
        }

        if (! $val) {
            return null;
        }

        // If already a full URL, return as is
        if (str_starts_with($val, 'http')) {
            return $val;
        }

        // Remove 'storage/' prefix if it exists
        $val = preg_replace('/^storage\//', '', $val);

        return url('storage/'.$val);
    }

    // Accessors
    /**
     * Override toArray to merge extended_profile fields into main object
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Merge extended_profile fields into main array for easier frontend access
        // Only merge keys that are in extendedFields - never let extended_profile overwrite main DB columns
        // (e.g. category_type, designation) which would show stale data after updates
        if (isset($array['extended_profile']) && is_array($array['extended_profile'])) {
            $extendedData = $array['extended_profile'];
            unset($array['extended_profile']);
            $safeExtended = array_intersect_key($extendedData, array_flip($this->extendedFields));
            $array = array_merge($array, $safeExtended);
        }

        // ✅ Ensure profile_picture uses the accessor (converts relative path to full URL)
        if (isset($array['profile_picture'])) {
            $array['profile_picture'] = $this->getProfilePictureAttribute();
        }

        return $array;
    }

    public function getFullNameAttribute()
    {
        $name = $this->first_name ?? $this->user->first_name ?? '';
        if ($this->middle_name) {
            $name .= ' '.$this->middle_name;
        }
        $name .= ' '.($this->last_name ?? $this->user->last_name ?? '');

        return trim($name);
    }

    public function getDisplayNameAttribute()
    {
        return $this->preferred_name ?: $this->getFullNameAttribute();
    }

    public function getProfileCompletionPercentageAttribute()
    {
        $totalFields = 50; // Total important fields
        $filledFields = 0;

        $importantFields = [
            'staff_id', 'category_type', 'designation', 'gender', 'date_of_birth',
            'pan_number', 'father_name', 'mother_name', 'current_address',
            'permanent_address', 'qualification', 'work_experience', 'basic_salary',
            'bank_account_number', 'bank_ifsc_code', 'emergency_contact_name',
            'emergency_contact_phone', 'aadhaar_number', 'blood_group',
            'marital_status', 'spouse_name', 'alternate_email', 'alternate_phone',
            'whatsapp_number', 'current_city', 'current_state', 'current_pincode',
            'permanent_city', 'permanent_state', 'permanent_pincode', 'epf_number',
            'pf_number', 'esi_number', 'uan_number', 'ctc', 'medical_history',
            'allergies', 'family_doctor_name', 'family_doctor_phone',
            'emergency_contact_2_name', 'emergency_contact_2_phone',
            'professional_memberships', 'hobbies_interests', 'volunteer_work',
            'community_involvement', 'personal_statement', 'career_objectives',
            'technical_skills', 'soft_skills', 'subject_expertise', 'notes',
        ];

        foreach ($importantFields as $field) {
            if (! empty($this->$field)) {
                $filledFields++;
            }
        }

        return round(($filledFields / $totalFields) * 100);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('teacher_status', 'Active');
    }

    public function scopeTeaching($query)
    {
        return $query->where('category_type', 'Teaching');
    }

    public function scopeNonTeaching($query)
    {
        return $query->where('category_type', 'Non-Teaching');
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    // Methods
    public function updateProfileCompletion()
    {
        // Just compute and return the percentage, don't save to database
        return $this->getProfileCompletionPercentageAttribute();
    }

    public function isProfileComplete()
    {
        return $this->getProfileCompletionPercentageAttribute() >= 80;
    }

    public function getRequiredDocuments()
    {
        $required = ['resume_path', 'joining_letter_path'];
        $optional = ['aadhaar_path', 'pan_path', 'passport_path', 'driving_license_path'];

        return [
            'required' => $required,
            'optional' => $optional,
            'uploaded' => array_filter([
                'resume' => $this->resume_path,
                'joining_letter' => $this->joining_letter_path,
                'aadhaar' => $this->aadhaar_path ?? null,
                'pan' => $this->pan_path ?? null,
                'passport' => $this->passport_path ?? null,
                'driving_license' => $this->driving_license_path ?? null,
            ]),
        ];
    }
}
