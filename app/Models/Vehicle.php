<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'school_id',
        'route_id',
        'driver_id',
        'transport_driver_id',
        'vehicle_number',
        'vehicle_type',
        'make',
        'model',
        'capacity',
        'insurance_expiry',
        'fitness_expiry',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'insurance_expiry' => 'date',
        'fitness_expiry' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'transport_driver_id');
    }
}
