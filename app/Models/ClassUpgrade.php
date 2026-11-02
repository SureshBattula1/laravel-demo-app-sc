<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassUpgrade extends Model
{
    protected $table = 'class_upgrades';

    protected $fillable = [
        'batch_id',
        'student_id',
        'academic_year_from',
        'academic_year_to',
        'from_academic_year_id',
        'to_academic_year_id',
        'from_grade',
        'to_grade',
        'promotion_status',
        'percentage',
        'approved_by',
        'fee_carry_forward_status',
        'fee_carry_forward_amount',
        'notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fee_carry_forward_amount' => 'decimal:2',
    ];

    /**
     * Get the promotion batch that owns this upgrade
     */
    public function promotionBatch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class, 'batch_id', 'id');
    }

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

    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    /**
     * Scope a query to only include promoted students
     */
    public function scopePromoted($query)
    {
        return $query->where('promotion_status', 'Promoted');
    }

    /**
     * Scope a query to filter by academic year (ID or name)
     */
    public function scopeForAcademicYear($query, $academicYear)
    {
        if (is_numeric($academicYear)) {
            $id = (int) $academicYear;

            return $query->where(function ($q) use ($id) {
                $q->where('from_academic_year_id', $id)
                    ->orWhere('to_academic_year_id', $id);
            });
        }

        return $query->where(function ($q) use ($academicYear) {
            $q->where('academic_year_from', $academicYear)
                ->orWhere('academic_year_to', $academicYear);
        });
    }
}
