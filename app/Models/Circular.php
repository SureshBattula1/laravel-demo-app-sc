<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Circular extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'circular_number',
        'title',
        'content',
        'type',
        'target_audience',
        'issue_date',
        'effective_date',
        'expiry_date',
        'priority',
        'requires_acknowledgment',
        'is_published',
        'published_at',
        'attachments',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'published_at' => 'datetime',
        'is_published' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'target_audience' => 'array',
        'attachments' => 'array'
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where('effective_date', '<=', now())
            ->where('expiry_date', '>=', now());
    }

    public function scopeActive($query)
    {
        return $query->where('effective_date', '<=', now())
            ->where('expiry_date', '>=', now());
    }
}

