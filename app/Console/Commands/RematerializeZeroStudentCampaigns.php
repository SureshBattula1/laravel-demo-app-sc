<?php

namespace App\Console\Commands;

use App\Models\NotificationCampaign;
use App\Services\NotificationCampaignDispatchService;
use App\Services\NotificationCampaignService;
use Illuminate\Console\Command;

class RematerializeZeroStudentCampaigns extends Command
{
    protected $signature = 'notification-campaigns:rematerialize-zero
                            {--module= : Limit to module (e.g. fees)}
                            {--id=* : Specific campaign id(s)}';

    protected $description = 'Rebuild recipients for campaigns with zero materialized students';

    public function handle(
        NotificationCampaignService $campaigns,
        NotificationCampaignDispatchService $dispatch,
    ): int {
        $query = NotificationCampaign::query()
            ->where('recipient_count', 0)
            ->whereIn('status', ['pending', 'materializing', 'failed', 'queued']);

        if ($module = $this->option('module')) {
            $query->where('module', (string) $module);
        }

        $ids = array_filter(array_map('intval', (array) $this->option('id')));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->info('No matching campaigns.');

            return self::SUCCESS;
        }

        foreach ($rows as $campaign) {
            $this->line("Campaign #{$campaign->id} ({$campaign->module})…");
            $campaigns->rematerializeCampaign($campaign);
            $campaign->refresh();
            $this->line("  → recipient_count={$campaign->recipient_count}, status={$campaign->status}");

            if ($campaign->status === 'queued') {
                $dispatch->start((int) $campaign->id);
            }
        }

        return self::SUCCESS;
    }
}
