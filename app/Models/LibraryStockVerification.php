<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryStockVerification extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id',
        'branch_id',
        'academic_year_id',
        'session_title',
        'verified_by',
        'started_at',
        'completed_at',
        'status',
        'total_copies_checked',
        'missing_count',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_copies_checked' => 'integer',
        'missing_count' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LibraryStockVerificationItem::class, 'verification_id');
    }
}
