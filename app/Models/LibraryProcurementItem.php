<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryProcurementItem extends Model
{
    protected $fillable = [
        'procurement_id',
        'book_id',
        'title',
        'author',
        'isbn',
        'publisher',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'total_price',
        'accession_status',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(LibraryProcurement::class, 'procurement_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }
}
