<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdCardScan extends Model
{
    protected $fillable = [
        'id_card_id',
        'scanner_user_id',
        'gate_name',
        'result',
        'reason',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(IdCard::class, 'id_card_id');
    }
}
