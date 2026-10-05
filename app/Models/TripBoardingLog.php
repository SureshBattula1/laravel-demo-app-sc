<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripBoardingLog extends Model
{
    protected $table = 'trip_boarding_logs';

    protected $fillable = [
        'trip_id',
        'student_id',
        'route_id',
        'pickup_stop_id',
        'drop_stop_id',
        'boarding_status',
        'boarded_at',
        'drop_status',
        'dropped_at',
        'marked_by',
        'verification_method',
    ];

    protected $casts = [
        'boarded_at' => 'datetime',
        'dropped_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TransportTrip::class, 'trip_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'pickup_stop_id');
    }

    public function dropStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'drop_stop_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
