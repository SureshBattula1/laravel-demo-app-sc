<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportTrip extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'transport_trips';

    protected $fillable = [
        'trip_code',
        'branch_id',
        'school_id',
        'route_id',
        'vehicle_id',
        'transport_driver_id',
        'trip_date',
        'trip_type',
        'status',
        'started_at',
        'completed_at',
        'current_stop_id',
        'current_latitude',
        'current_longitude',
        'current_speed',
        'odometer_start',
        'odometer_end',
        'notes',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'current_speed' => 'decimal:2',
        'odometer_start' => 'integer',
        'odometer_end' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'transport_driver_id');
    }

    public function currentStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'current_stop_id');
    }

    public function boardingLogs(): HasMany
    {
        return $this->hasMany(TripBoardingLog::class, 'trip_id');
    }
}
