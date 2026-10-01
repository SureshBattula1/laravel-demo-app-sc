<?php

namespace App\Console\Commands;

use App\Models\NotificationCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseCampaignQueues extends Command
{
    protected $signature = 'campaigns:diagnose';

    protected $description = 'Check queue config and in-progress notification campaigns';

    public function handle(): int
    {
        $driver = config('queue.default');
        $this->info('Queue driver: '.$driver);
        $this->line('Send chunk size: '.config('notification_campaigns.send_chunk_size'));
        $this->line('Queues: '.implode(', ', config('notification_campaigns.queues')));

        if ($driver === 'database' && Schema::hasTable('jobs')) {
            $pendingJobs = (int) DB::table('jobs')->count();
            $this->line("Pending jobs (all queues): {$pendingJobs}");
        }

        if ($driver === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync runs jobs inside HTTP requests — use database/redis + queue:work for large campaigns.');
        }

        $active = NotificationCampaign::query()
            ->whereIn('status', ['materializing', 'queued', 'sending', 'pending'])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'module', 'status', 'recipient_count', 'sent_count', 'failed_count']);

        if ($active->isEmpty()) {
            $this->info('No active campaigns in the last check set.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'module', 'status', 'recipients', 'sent', 'failed'],
            $active->map(fn ($c) => [
                $c->id,
                $c->module,
                $c->status,
                $c->recipient_count,
                $c->sent_count,
                $c->failed_count,
            ])->all()
        );

        $this->line('');
        $this->line('Worker example:');
        $this->line('  php artisan queue:work '.$driver.' --queue=campaigns,campaigns-materialize,campaigns-send --tries=3 --timeout=120');

        return self::SUCCESS;
    }
}
