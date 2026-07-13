<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered stop on a transport route. Scoped indirectly through its route
 * (route access is branch-guarded in the controller), so no global tenant scope here.
 */
class RouteStop extends Model
{
    protected $fillable = [
        'route_id',
        'branch_id',
        'school_id',
        'sequence_no',
        'stop_name',
        'pickup_time',
        'drop_time',
        'latitude',
        'longitude',
        'geofence_radius',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geofence_radius' => 'integer',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }
}
