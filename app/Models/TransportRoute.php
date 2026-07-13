<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportRoute extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'school_id',
        'route_number',
        'route_name',
        'description',
        'stops',
        'distance',
        'estimated_time',
        'fare',
        'is_active',
    ];

    protected $casts = [
        'stops' => 'array',
        'distance' => 'decimal:2',
        'estimated_time' => 'integer',
        'fare' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('sequence_no');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'route_id');
    }

    public function studentTransports(): HasMany
    {
        return $this->hasMany(StudentTransport::class, 'route_id');
    }
}
