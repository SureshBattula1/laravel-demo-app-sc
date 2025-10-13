<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FeePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'fee_structure_id',
        'student_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'transaction_id',
        'receipt_number',
        'discount_amount',
        'late_fee',
        'total_amount',
        'payment_status',
        'remarks',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_date' => 'datetime'
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
            // Auto-generate receipt number
            if (empty($model->receipt_number)) {
                $model->receipt_number = 'RCPT-' . strtoupper(substr(Str::uuid(), 0, 8));
            }
        });
    }

    // Relationships
    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
