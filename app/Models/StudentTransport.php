<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransport extends Model
{
    use BelongsToTenant;

    protected $table = 'student_transport';

    protected $fillable = [
        'student_id',
        'route_id',
        'vehicle_id',
        'branch_id',
        'school_id',
        'stop_name',
        'pickup_stop_id',
        'drop_stop_id',
        'pickup_time',
        'drop_time',
        'annual_fee',
        'monthly_fee',
        'due_date',
        'status',
    ];

    protected $casts = [
        'annual_fee' => 'decimal:2',
        'monthly_fee' => 'decimal:2',
        'due_date' => 'date',
    ];

    protected $appends = [
        'annual_fee',
    ];

    public function getAnnualFeeAttribute($value)
    {
        return $value !== null ? (float) $value : (isset($this->attributes['monthly_fee']) ? (float) $this->attributes['monthly_fee'] : 0);
    }

    public function setAnnualFeeAttribute($value)
    {
        $this->attributes['annual_fee'] = $value;
        if (! isset($this->attributes['monthly_fee']) || empty($this->attributes['monthly_fee'])) {
            $this->attributes['monthly_fee'] = $value;
        }
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
