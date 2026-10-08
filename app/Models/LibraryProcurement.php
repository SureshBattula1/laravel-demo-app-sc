<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryProcurement extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'school_id',
        'branch_id',
        'po_number',
        'vendor_name',
        'vendor_contact',
        'order_date',
        'delivery_date',
        'total_amount',
        'status',
        'invoice_number',
        'created_by',
        'remarks',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LibraryProcurementItem::class, 'procurement_id');
    }
}
