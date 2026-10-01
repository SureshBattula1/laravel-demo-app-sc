<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationCampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'target_id',
        'user_id',
        'student_id',
        'student_name',
        'grade',
        'section',
        'status_key',
        'context',
        'delivery_status',
        'error',
        'notification_id',
        'viewed_at',
        'liked_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'liked_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
