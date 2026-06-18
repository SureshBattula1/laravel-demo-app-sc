<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FeeDue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'student_id',
        'fee_structure_id',
        'academic_year',
        'original_grade',
        'current_grade',
        'original_amount',
        'paid_amount',
        'balance_amount',
        'due_date',
        'overdue_days',
        'status',
        'carry_forward_date',
        'carry_forward_reason',
        'fee_type',
        'installment_number',
        'metadata'
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'due_date' => 'date',
        'carry_forward_date' => 'date',
        'overdue_days' => 'integer',
        'installment_number' => 'integer',
        'metadata' => 'array'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the student that owns the fee due
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the fee structure related to this due
     */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    /**
     * Scope a query to only include pending dues
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    /**
     * Scope a query to only include overdue dues
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'Overdue')
            ->orWhere(function($q) {
                $q->where('status', 'Pending')
                  ->where('due_date', '<', now());
            });
    }

    /**
     * Scope a query to filter by fee type
     */
    public function scopeByFeeType($query, $feeType)
    {
        return $query->where('fee_type', $feeType);
    }

    /**
     * Scope a query to filter by academic year
     */
    public function scopeForAcademicYear($query, $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    /**
     * Calculate aging bucket
     */
    public function getAgingBucketAttribute(): string
    {
        if ($this->overdue_days <= 30) {
            return '0-30';
        } elseif ($this->overdue_days <= 60) {
            return '31-60';
        } elseif ($this->overdue_days <= 90) {
            return '61-90';
        } else {
            return '90+';
        }
    }
}

