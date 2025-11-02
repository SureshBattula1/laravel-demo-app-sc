<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'branch_id',
        'is_mandatory',
        'is_refundable',
        'is_active',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_refundable' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the branch that owns the fee type
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the fee structures for this fee type
     * Note: Fee structures use fee_type as a string field, not a foreign key
     */
    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'fee_type', 'name');
    }

    /**
     * Scope a query to only include active fee types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by branch
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope a query to only include mandatory fee types
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }
}

