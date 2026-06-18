<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    public const AUDIENCE_STUDENT = 'student';

    public const AUDIENCE_TEACHER = 'teacher';

    public const AUDIENCE_BOTH = 'both';

    public const AUDIENCES = [
        self::AUDIENCE_STUDENT,
        self::AUDIENCE_TEACHER,
        self::AUDIENCE_BOTH,
    ];

    protected $fillable = [
        'branch_id',
        'name',
        'body',
        'audience',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether this template can be used for the given recipient kind.
     */
    public function matchesRecipientType(string $recipientType): bool
    {
        if ($this->audience === self::AUDIENCE_BOTH) {
            return true;
        }

        return $this->audience === $recipientType;
    }
}
