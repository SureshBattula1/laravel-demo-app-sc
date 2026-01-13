<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FeeStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'branch_id',
        'grade',
        'fee_type',
        'amount',
        'academic_year',
        'due_date',
        'description',
        'is_recurring',
        'recurrence_period',
        'is_active',
        'created_by',
        'updated_by',
        // Breakdown fields (optional - for detailed fee breakdown if provided by frontend)
        'tuition_fee',
        'admission_fee',
        'exam_fee',
        'library_fee',
        'transport_fee',
        'sports_fee',
        'lab_fee',
        'other_fees',
        'total_amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        // Breakdown fields casting
        'tuition_fee' => 'decimal:2',
        'admission_fee' => 'decimal:2',
        'exam_fee' => 'decimal:2',
        'library_fee' => 'decimal:2',
        'transport_fee' => 'decimal:2',
        'sports_fee' => 'decimal:2',
        'lab_fee' => 'decimal:2',
        'other_fees' => 'array', // JSON field
        'total_amount' => 'decimal:2'
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

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
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
