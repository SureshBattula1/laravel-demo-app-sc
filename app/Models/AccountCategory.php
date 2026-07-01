<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountCategory extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'school_id',
        'academic_year_id',
        'name',
        'code',
        'type',
        'sub_type',
        'description',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Academic year that owns this category (optional).
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'category_id');
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'category_id');
    }

    // Scopes
    public function scopeIncome($query)
    {
        return $query->where('type', 'Income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'Expense');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

