<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherImport extends Model
{
    protected $fillable = [
        'batch_id',
        'row_number',
        'branch_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'employee_id',
        'joining_date',
        'leaving_date',
        'designation',
        'employee_type',
        'qualification',
        'experience_years',
        'specialization',
        'registration_number',
        'subjects',
        'classes_assigned',
        'is_class_teacher',
        'class_teacher_of_grade',
        'class_teacher_of_section',
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
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'salary_grade',
        'basic_salary',
        'bank_name',
        'bank_account_number',
        'bank_ifsc_code',
        'pan_number',
        'aadhar_number',
        'password',
        'remarks',
        'validation_status',
        'validation_errors',
        'validation_warnings',
        'imported_to_production',
        'imported_user_id',
        'imported_teacher_id',
        'imported_at',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'leaving_date' => 'date',
        'date_of_birth' => 'date',
        'experience_years' => 'decimal:2',
        'is_class_teacher' => 'boolean',
        'basic_salary' => 'decimal:2',
        'validation_errors' => 'array',
        'validation_warnings' => 'array',
        'imported_to_production' => 'boolean',
        'imported_at' => 'datetime',
    ];

    /**
     * Get the import history this belongs to
     */
    public function importHistory(): BelongsTo
    {
        return $this->belongsTo(ImportHistory::class, 'batch_id', 'batch_id');
    }

    /**
     * Get the created user (if imported)
     */
    public function importedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_user_id');
    }

    /**
     * Scope to get valid records
     */
    public function scopeValid($query)
    {
        return $query->where('validation_status', 'valid');
    }

    /**
     * Scope to get invalid records
     */
    public function scopeInvalid($query)
    {
        return $query->where('validation_status', 'invalid');
    }

    /**
     * Scope to get pending validation records
     */
    public function scopePending($query)
    {
        return $query->where('validation_status', 'pending');
    }

    /**
     * Scope to get not yet imported records
     */
    public function scopeNotImported($query)
    {
        return $query->where('imported_to_production', false);
    }

    /**
     * Check if record has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->validation_errors);
    }

    /**
     * Check if record has warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->validation_warnings);
    }
}

