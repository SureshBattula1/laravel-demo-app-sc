<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignDispatchService;
use App\Services\NotificationCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SendCampaignRecipientsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $campaignId) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('nc-campaign-send-'.$this->campaignId))->releaseAfter(90),
            new RateLimited('nc-send-chunks'),
        ];
    }

    public function handle(
        NotificationCampaignService $campaigns,
        NotificationCampaignDispatchService $dispatch,
    ): void {
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        if (! in_array($campaign->status, ['queued', 'sending', 'pending', 'partial'], true)) {
            if ($campaign->status === 'materializing') {
                return;
            }
        }

        $result = $campaigns->sendNextChunk($campaign);
        if ($result['processed'] === 0) {
            FinalizeCampaignRollupJob::dispatch($this->campaignId)
                ->onQueue(config('notification_campaigns.queues.orchestrator'));

            return;
        }

        $dispatch->afterSendChunk($this->campaignId, $result['sent'], $result['failed']);
    }
}
