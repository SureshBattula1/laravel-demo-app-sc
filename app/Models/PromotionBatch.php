<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionBatch extends Model
{
    use BelongsToTenant;

    protected $table = 'promotion_batches';

    protected $fillable = [
        'batch_id',
        'school_id',
        'branch_id',
        'from_academic_year_id',
        'to_academic_year_id',
        'from_grade',
        'to_grade',
        'from_section',
        'to_section',
        'total_students',
        'promoted_count',
        'retained_count',
        'reverted_count',
        'total_fees_carried_forward',
        'status', // 'completed', 'partially_reverted', 'reverted'
        'executed_by',
        'reverted_by',
        'reverted_at',
        'metadata',
    ];

    protected $casts = [
        'total_students' => 'integer',
        'promoted_count' => 'integer',
        'retained_count' => 'integer',
        'reverted_count' => 'integer',
        'total_fees_carried_forward' => 'decimal:2',
        'metadata' => 'array',
        'reverted_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function reverter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function classUpgrades(): HasMany
    {
        return $this->hasMany(ClassUpgrade::class, 'batch_id', 'id');
    }
}
