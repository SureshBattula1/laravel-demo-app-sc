<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportExpense extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'transport_expenses';

    protected $fillable = [
        'branch_id',
        'school_id',
        'vehicle_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'paid_by',
        'reference_no',
        'attachment_url',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
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
