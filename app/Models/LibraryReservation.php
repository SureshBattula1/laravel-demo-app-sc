<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryReservation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id',
        'branch_id',
        'book_id',
        'copy_id',
        'member_id',
        'borrower_type',
        'reserved_at',
        'hold_until',
        'status',
        'notified_at',
        'notes',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'hold_until' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryBookCopy::class, 'copy_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
