<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryStockVerificationItem extends Model
{
    protected $fillable = [
        'verification_id',
        'copy_id',
        'scanned_barcode',
        'shelf_id',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(LibraryStockVerification::class, 'verification_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryBookCopy::class, 'copy_id');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(LibraryShelf::class, 'shelf_id');
    }
}
