<?php

namespace App\Services;

use App\Jobs\FinalizeCampaignRollupJob;
use App\Jobs\MaterializeCampaignRecipientsChunkJob;
use App\Jobs\SendCampaignRecipientsChunkJob;
use App\Jobs\StartNotificationCampaignJob;
use App\Models\NotificationCampaign;

class NotificationCampaignDispatchService
{
    public function start(int $campaignId): void
    {
        StartNotificationCampaignJob::dispatch($campaignId)
            ->onQueue(config('notification_campaigns.queues.orchestrator'));
    }

    public function orchestrate(NotificationCampaign $campaign): void
    {
        $campaign->refresh();

        if (in_array($campaign->status, ['sent', 'failed'], true)) {
            return;
        }

        if ($campaign->status === 'materializing') {
            MaterializeCampaignRecipientsChunkJob::dispatch($campaign->id)
                ->onQueue(config('notification_campaigns.queues.materialize'));

            return;
        }

        if ($this->shouldSend($campaign)) {
            SendCampaignRecipientsChunkJob::dispatch($campaign->id)
                ->onQueue(config('notification_campaigns.queues.send'));
        }
    }

    public function afterSendChunk(int $campaignId, int $sentDelta, int $failedDelta): void
    {
        $campaign = NotificationCampaign::query()->find($campaignId);
        if (!$campaign) {
            return;
        }

        $pending = $campaign->recipients()
            ->whereIn('delivery_status', ['pending', 'processing'])
            ->exists();

        if (!$pending) {
            FinalizeCampaignRollupJob::dispatch($campaignId)
                ->onQueue(config('notification_campaigns.queues.orchestrator'));

            return;
        }

        FinalizeCampaignRollupJob::dispatch($campaignId)
            ->delay(now()->addSeconds(config('notification_campaigns.rollup_debounce_seconds')))
            ->onQueue(config('notification_campaigns.queues.orchestrator'));

        SendCampaignRecipientsChunkJob::dispatch($campaignId)
            ->onQueue(config('notification_campaigns.queues.send'));
    }

    public function afterMaterializeChunk(int $campaignId): void
    {
        $campaign = NotificationCampaign::query()->find($campaignId);
        if (!$campaign) {
            return;
        }

        if ($campaign->status === 'materializing') {
            MaterializeCampaignRecipientsChunkJob::dispatch($campaignId)
                ->onQueue(config('notification_campaigns.queues.materialize'));

            return;
        }

        if ($this->shouldSend($campaign)) {
            $campaign->update(['status' => 'queued']);
            SendCampaignRecipientsChunkJob::dispatch($campaignId)
                ->onQueue(config('notification_campaigns.queues.send'));
        }
    }

    private function shouldSend(NotificationCampaign $campaign): bool
    {
        if ($campaign->recipient_count <= 0) {
            return false;
        }

        return $campaign->recipients()->where('delivery_status', 'pending')->exists();
    }
}
