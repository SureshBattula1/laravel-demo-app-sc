<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignDispatchService;
use App\Services\NotificationCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class MaterializeCampaignRecipientsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $campaignId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('nc-campaign-materialize-'.$this->campaignId))->releaseAfter(120),
        ];
    }

    public function handle(
        NotificationCampaignService $campaigns,
        NotificationCampaignDispatchService $dispatch,
    ): void {
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if (!$campaign || $campaign->status !== 'materializing') {
            return;
        }

        $campaigns->materializeNextChunk($campaign);
        $dispatch->afterMaterializeChunk($this->campaignId);
    }
}
