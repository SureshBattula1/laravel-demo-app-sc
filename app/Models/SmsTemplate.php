<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    use BelongsToTenant;

    public const AUDIENCE_STUDENT = 'student';

    public const AUDIENCE_TEACHER = 'teacher';

    public const AUDIENCE_BOTH = 'both';

    public const AUDIENCES = [
        self::AUDIENCE_STUDENT,
        self::AUDIENCE_TEACHER,
        self::AUDIENCE_BOTH,
    ];

    /** Notification campaign module slugs (matches schedule routes). */
    public const MODULE_HOLIDAYS = 'holidays';

    public const MODULE_EXAMS = 'exams';

    public const MODULE_ATTENDANCE = 'attendance';

    public const MODULE_FEES = 'fees';

    public const MODULE_ASSIGNMENTS = 'assignments';

    public const MODULE_CUSTOM = 'custom';

    public const MODULE_TYPES = [
        self::MODULE_HOLIDAYS,
        self::MODULE_EXAMS,
        self::MODULE_ATTENDANCE,
        self::MODULE_FEES,
        self::MODULE_ASSIGNMENTS,
        self::MODULE_CUSTOM,
    ];

    protected $fillable = [
        'branch_id',
        'name',
        'body',
        'audience',
        'module_type',
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
     * Map schedule module (e.g. teacher_attendance) to template module_type.
     */
    public static function normalizeCampaignModule(?string $module): ?string
    {
        if ($module === null || $module === '') {
            return null;
        }
        $module = strtolower(trim($module));
        if ($module === 'teacher_attendance') {
            return self::MODULE_ATTENDANCE;
        }

        return in_array($module, self::MODULE_TYPES, true) ? $module : null;
    }

    /**
     * Templates for a campaign schedule step (exact module_type only).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     */
    public function scopeForCampaignModule($query, ?string $module)
    {
        $normalized = self::normalizeCampaignModule($module);
        if ($normalized === null) {
            return $query;
        }

        return $query->where('module_type', $normalized);
    }

    /** Whether template may be used for this campaign module (legacy null = any module). */
    public function matchesCampaignModule(?string $module): bool
    {
        $normalized = self::normalizeCampaignModule($module);
        if ($normalized === null) {
            return true;
        }
        if ($this->module_type === null || $this->module_type === '') {
            return true;
        }

        return $this->module_type === $normalized;
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
