<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportStop extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'transport_stops_master';

    protected $fillable = [
        'branch_id',
        'school_id',
        'stop_name',
        'location',
        'landmark',
        'latitude',
        'longitude',
        'geofence_radius',
        'default_pickup_time',
        'default_drop_time',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geofence_radius' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
