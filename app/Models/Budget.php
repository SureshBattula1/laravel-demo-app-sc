<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'category_id',
        'financial_year',
        'allocated_amount',
        'utilized_amount',
        'remaining_amount',
        'notes',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'utilized_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(AccountCategory::class, 'category_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'category_id', 'category_id')
            ->where('financial_year', $this->financial_year);
    }

    // Helper methods
    public function getUtilizationPercentage()
    {
        if ($this->allocated_amount == 0) {
            return 0;
        }
        return round(($this->utilized_amount / $this->allocated_amount) * 100, 2);
    }

    public function isOverBudget()
    {
        return $this->utilized_amount > $this->allocated_amount;
    }
}

