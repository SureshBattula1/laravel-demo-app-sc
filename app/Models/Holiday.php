<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',  // Old column (keep for compatibility)
        'title',
        'description',
        'start_date',
        'end_date',
        'date', // Old column (keep for compatibility)
        'type',
        'color',
        'is_recurring',
        'academic_year',
        'is_active',
        'created_by'
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get duration in days
     */
    public function getDurationAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Check if holiday is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->start_date->isFuture();
    }

    /**
     * Check if holiday is ongoing
     */
    public function isOngoing(): bool
    {
        $now = now();
        return $this->start_date->lte($now) && $this->end_date->gte($now);
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopeOngoing($query)
    {
        return $query->where('start_date', '<=', now())
                     ->where('end_date', '>=', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->where(function($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
              ->orWhereBetween('end_date', [$start, $end])
              ->orWhere(function($q2) use ($start, $end) {
                  $q2->where('start_date', '<=', $start)
                     ->where('end_date', '>=', $end);
              });
        });
    }
}
