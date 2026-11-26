<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdCard extends Model
{
    protected $fillable = [
        'user_id',
        'person_type',
        'branch_id',
        'school_id',
        'academic_year_id',
        'card_number',
        'status',
        'activated_at',
        'expires_at',
        'replaced_by_id',
        'created_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(IdCardScan::class);
    }
}
