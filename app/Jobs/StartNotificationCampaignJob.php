<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartNotificationCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $campaignId) {}

    public function handle(NotificationCampaignDispatchService $dispatch): void
    {
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if (!$campaign) {
            return;
        }

        if ($campaign->status === 'pending' && $campaign->recipient_count > 0) {
            $campaign->update(['status' => 'queued']);
        }

        $dispatch->orchestrate($campaign->fresh());
    }
}
