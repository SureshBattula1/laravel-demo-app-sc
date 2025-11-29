<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    // ONLY actual database columns (verified via Schema::getColumnListing)
    protected $fillable = [
        'id', 'user_id', 'branch_id', 'department_id', 'reporting_manager_id',
        'employee_id', 'category_type', 'joining_date', 'designation', 'employee_type',
        'subjects', 'classes_assigned', 'is_class_teacher', 'date_of_birth', 'gender',
        'current_address', 'permanent_address', 'city', 'state', 'pincode',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'basic_salary', 'bank_account_number', 'teacher_status',
        'created_at', 'updated_at', 'deleted_at', 'extended_profile',
        'bank_name', 'bank_ifsc_code', 'pan_number', 'aadhar_number',
        'salary_grade', 'specialization', 'registration_number',
        'class_teacher_of_grade', 'class_teacher_of_section', 'leaving_date',
        'blood_group', 'religion', 'nationality', 'qualification', 'experience_years',
        'documents', 'remarks'
    ];
    
    // Virtual attributes to append to JSON output
    // These will pull from extended_profile via their accessors
    protected $appends = ['profile_picture', 'aadhaar_number'];
    
    protected $extendedFields = [
        'first_name', 'middle_name', 'last_name', 'preferred_name', 'title', 'suffix',
        'passport_number', 'passport_expiry', 'driving_license_number', 'driving_license_expiry',
        'voter_id', 'place_of_birth', 'religion', 'caste', 'sub_caste', 'mother_tongue',
        'languages_known', 'handicap_status', 'handicap_details', 'spouse_name',
        'spouse_date_of_birth', 'spouse_occupation', 'spouse_phone', 'spouse_email',
        'number_of_children', 'children_details', 'alternate_email', 'landline_number',
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
        'esi_number', 'uan_number', 'gratuity_number', 'salary_components', 'deductions',
        'income_tax_pan', 'account_title', 'bank_branch_name', 'previous_employers',
        'references', 'professional_memberships', 'professional_license',
        'performance_reviews', 'appraisals', 'goals_objectives', 'training_needs',
        'hobbies_interests', 'volunteer_work', 'community_involvement',
        'personal_statement', 'career_objectives', 'notes', 'additional_notes'
    ];

    protected function casts(): array
    {
        return [
            'is_class_teacher' => 'boolean',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'basic_salary' => 'decimal:2',
            'ctc' => 'decimal:2',
            'subjects' => 'array',
            'classes_assigned' => 'array',
            'extended_profile' => 'array',
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
            'training_needs' => 'array'
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
    public function getQualificationAttribute($value)
    {
        // First check actual database column, then extended_profile for backward compatibility
        if (isset($this->attributes['qualification'])) {
            return $this->attributes['qualification'];
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
    
    // ✅ Add accessor for aadhaar_number (frontend expects this name, DB has aadhar_number)
    public function getAadhaarNumberAttribute()
    {
        return $this->attributes['aadhar_number'] ?? null;
    }

    // Profile picture URL accessor
    // Since profile_picture is not a DB column, it's stored in extended_profile JSON
    public function getProfilePictureAttribute()
    {
        $value = null;
        
        // Extract from extended_profile JSON
        if (isset($this->attributes['extended_profile'])) {
            $extendedProfile = is_string($this->attributes['extended_profile']) 
                ? json_decode($this->attributes['extended_profile'], true) 
                : $this->attributes['extended_profile'];
            
            $value = $extendedProfile['profile_picture'] ?? null;
        }
        
        if (!$value) {
            return null;
        }
        
        // If already a full URL, return as is
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        // Remove 'storage/' prefix if it exists (we'll add it back)
        $value = preg_replace('/^storage\//', '', $value);
        
        // Construct full URL with /storage/ prefix
        // The file path includes 'uploads/' so keep it: uploads/teachers/31/profile_picture
        return url('storage/' . $value);
    }

    // Accessors
    /**
     * Override toArray to merge extended_profile fields into main object
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Merge extended_profile fields into main array for easier frontend access
        if (isset($array['extended_profile']) && is_array($array['extended_profile'])) {
            $extendedData = $array['extended_profile'];
            unset($array['extended_profile']);  // Remove the nested object
            $array = array_merge($array, $extendedData);  // Merge fields to root level
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
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . ($this->last_name ?? $this->user->last_name ?? '');
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
            'technical_skills', 'soft_skills', 'subject_expertise', 'notes'
        ];

        foreach ($importantFields as $field) {
            if (!empty($this->$field)) {
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
                'driving_license' => $this->driving_license_path ?? null
            ])
        ];
    }
}
