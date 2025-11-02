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
        'roll_number',
        'grade',
        'section',
        'academic_year',
        'date_of_birth',
        'gender',
        'blood_group',
        'religion',
        'nationality',
        'current_address',
        'permanent_address',
        'city',
        'state',
        'pincode',
        'father_name',
        'father_phone',
        'father_email',
        'father_occupation',
        'mother_name',
        'mother_phone',
        'mother_email',
        'mother_occupation',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'previous_school',
        'previous_grade',
        'medical_history',
        'allergies',
        'student_status',
        'profile_picture',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'date_of_birth' => 'date',
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
