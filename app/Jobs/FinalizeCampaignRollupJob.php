<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeCampaignRollupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $campaignId) {}

    public function handle(NotificationCampaignService $campaigns): void
    {
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if (!$campaign) {
            return;
        }

        $campaigns->rollup($campaign);
    }
}
