<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassUpgrade extends Model
{
    protected $table = 'class_upgrades';
    
    protected $fillable = [
        'student_id',
        'academic_year_from',
        'academic_year_to',
        'from_grade',
        'to_grade',
        'promotion_status',
        'percentage',
        'approved_by',
        'fee_carry_forward_status',
        'fee_carry_forward_amount',
        'notes'
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fee_carry_forward_amount' => 'decimal:2'
    ];

    /**
     * Get the student that owns the promotion record
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who approved the promotion
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope a query to only include promoted students
     */
    public function scopePromoted($query)
    {
        return $query->where('promotion_status', 'Promoted');
    }

    /**
     * Scope a query to filter by academic year
     */
    public function scopeForAcademicYear($query, $academicYear)
    {
        return $query->where('academic_year_from', $academicYear)
            ->orWhere('academic_year_to', $academicYear);
    }
}

