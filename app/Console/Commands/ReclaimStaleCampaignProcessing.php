<?php

namespace App\Console\Commands;

use App\Services\NotificationCampaignService;
use Illuminate\Console\Command;

class ReclaimStaleCampaignProcessing extends Command
{
    protected $signature = 'campaigns:reclaim-stale-processing {--minutes=}';

    protected $description = 'Reset notification campaign recipients stuck in processing back to pending';

    public function handle(NotificationCampaignService $campaigns): int
    {
        $minutes = $this->option('minutes');
        $count = $campaigns->reclaimStaleProcessing($minutes !== null ? (int) $minutes : null);
        $this->info("Reclaimed {$count} recipient row(s).");

        return self::SUCCESS;
    }
}
