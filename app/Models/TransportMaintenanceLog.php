<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportMaintenanceLog extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'transport_maintenance_logs';

    protected $fillable = [
        'branch_id',
        'school_id',
        'vehicle_id',
        'service_date',
        'service_type',
        'description',
        'amount',
        'garage_name',
        'next_service_date',
        'next_service_km',
        'status',
        'created_by',
    ];

    protected $casts = [
        'service_date' => 'date',
        'next_service_date' => 'date',
        'amount' => 'decimal:2',
        'next_service_km' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
