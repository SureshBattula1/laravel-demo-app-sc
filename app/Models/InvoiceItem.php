<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'transaction_id',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'discount_amount',
        'amount'
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    // Relationships
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}

