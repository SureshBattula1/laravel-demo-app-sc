<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $campaignId) {}

    public static function dispatchFor(int $campaignId): void
    {
        $job = new static($campaignId);
        $service = app(NotificationCampaignService::class);
        do {
            $job->handle($service);
            $pending = NotificationCampaign::query()
                ->find($campaignId)
                ?->recipients()
                ->where('delivery_status', 'pending')
                ->exists();
        } while ($pending);
    }

    public function handle(NotificationCampaignService $service): void
    {
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if (!$campaign) {
            return;
        }
        $service->sendPending($campaign);
    }
}
