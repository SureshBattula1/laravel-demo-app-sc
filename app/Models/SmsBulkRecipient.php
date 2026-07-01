<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsBulkRecipient extends Model
{
    use BelongsToTenant;
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'sms_bulk_queue_id',
        'branch_id',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'status',
        'error_message',
        'provider_meta',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_meta' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(SmsBulkQueue::class, 'sms_bulk_queue_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
