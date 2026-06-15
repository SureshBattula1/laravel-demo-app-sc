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
     * Whether this academic year is treated as ended for write restrictions.
     * The designated current year (is_current) stays editable even if end_date was not extended yet.
     */
    public function isPast(): bool
    {
        if ($this->is_current) {
            return false;
        }

        return $this->end_date->copy()->endOfDay()->isPast();
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
