<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryBookCopy extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'school_id',
        'branch_id',
        'book_id',
        'accession_number',
        'barcode',
        'copy_number',
        'shelf_id',
        'condition',
        'status',
        'purchase_price',
        'purchase_date',
        'vendor_name',
        'remarks',
        'is_active',
    ];

    protected $casts = [
        'copy_number' => 'integer',
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(LibraryShelf::class, 'shelf_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class, 'copy_id');
    }

    public function activeIssue()
    {
        return $this->hasOne(BookIssue::class, 'copy_id')->where('status', 'Issued')->latestOfMany();
    }
}
