<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    protected $fillable = [
        'module',
        'branch_id',
        'event_date',
        'scheduled_at',
        'status',
        'template_map',
        'target_count',
        'recipient_count',
        'sent_count',
        'failed_count',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'scheduled_at' => 'datetime',
        'template_map' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NotificationCampaignTarget::class, 'campaign_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationCampaignRecipient::class, 'campaign_id');
    }
}
