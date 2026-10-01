<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaignTarget extends Model
{
    protected $fillable = [
        'campaign_id',
        'grade',
        'section',
        'student_count',
        'status',
        'sent_count',
        'failed_count',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationCampaignRecipient::class, 'target_id');
    }
}
