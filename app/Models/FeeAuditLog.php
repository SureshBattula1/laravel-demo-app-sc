<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at, no updated_at

    protected $fillable = [
        'id',
        'action',
        'student_id',
        'fee_payment_id',
        'fee_due_id',
        'amount_before',
        'amount_after',
        'action_amount',
        'reason',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'request_method',
        'request_endpoint',
        'created_by',
        'created_at'
    ];

    protected $casts = [
        'amount_before' => 'decimal:2',
        'amount_after' => 'decimal:2',
        'action_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    /**
     * Get the student related to this audit log
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the fee payment related to this audit log
     */
    public function feePayment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class);
    }

    /**
     * Get the fee due related to this audit log
     */
    public function feeDue(): BelongsTo
    {
        return $this->belongsTo(FeeDue::class);
    }

    /**
     * Get the user who performed the action
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to filter by action type
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by student
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope a query to filter by date range
     */
    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('created_at', [$fromDate, $toDate]);
    }
}

