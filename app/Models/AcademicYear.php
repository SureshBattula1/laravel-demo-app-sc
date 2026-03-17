<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class AcademicYear extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'is_active',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: current academic year (is_current = true).
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: active academic years only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this academic year has ended (end_date is in the past).
     */
    public function isPast(): bool
    {
        return $this->end_date->isPast();
    }

    /**
     * Whether the given date falls within this academic year (inclusive).
     */
    public function containsDate($date): bool
    {
        $d = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $d->between($this->start_date, $this->end_date);
    }
}
