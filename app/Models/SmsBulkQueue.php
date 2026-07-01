<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsBulkQueue extends Model
{
    use BelongsToTenant;
    protected $table = 'sms_bulk_queue';

    protected $fillable = [
        'branch_id',
        'academic_year_id',
        'channel',
        'user_id',
        'template_id',
        'audience',
        'body_template',
        'sample_rendered',
        'sample_label',
        'recipient_count',
        'job_count',
        'provider',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsBulkRecipient::class, 'sms_bulk_queue_id');
    }

    public static function maybeMarkCompleted(int $queueId): void
    {
        // Recipient `pending` with a Twilio `message_sid` means the message was accepted; delivery
        // may still update via webhook. Do not keep the whole batch stuck in `processing` for that case.
        $stillProcessing = SmsBulkRecipient::query()
            ->where('sms_bulk_queue_id', $queueId)
            ->where('status', SmsBulkRecipient::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('provider_meta')
                    ->orWhereNull('provider_meta->message_sid')
                    ->orWhere('provider_meta->message_sid', '');
            })
            ->exists();

        if (! $stillProcessing) {
            self::query()->whereKey($queueId)->update(['status' => 'completed']);
        }
    }
}
