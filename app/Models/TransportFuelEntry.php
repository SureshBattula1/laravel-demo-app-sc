<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportFuelEntry extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'transport_fuel_entries';

    protected $fillable = [
        'branch_id',
        'school_id',
        'vehicle_id',
        'entry_date',
        'fuel_type',
        'quantity_litres',
        'rate_per_litre',
        'total_amount',
        'odometer_reading',
        'mileage_calculated',
        'invoice_no',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'quantity_litres' => 'decimal:2',
        'rate_per_litre' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'odometer_reading' => 'integer',
        'mileage_calculated' => 'decimal:2',
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
