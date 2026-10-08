<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryFine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id',
        'branch_id',
        'book_issue_id',
        'member_id',
        'borrower_type',
        'type',
        'amount',
        'paid_amount',
        'waived_amount',
        'status',
        'payment_method',
        'transaction_reference',
        'waived_reason',
        'collected_by',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'waived_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(BookIssue::class, 'book_issue_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
