<?php

namespace App\Console\Commands;

use App\Models\StudentTransport;
use App\Services\StudentTransportFeeSyncService;
use Illuminate\Console\Command;

class SyncTransportFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transport:sync-fees {--student_id= : Sync only specific student user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize student transport assignments with student profile fields and fee dues';

    /**
     * Execute the console command.
     */
    public function handle(StudentTransportFeeSyncService $syncService): int
    {
        $this->info('Starting transport fee and profile synchronization...');

        $query = StudentTransport::with(['route', 'vehicle']);
        if ($studentId = $this->option('student_id')) {
            $query->where('student_id', $studentId);
        }

        $assignments = $query->get();
        if ($assignments->isEmpty()) {
            $this->warn('No student transport assignments found to sync.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($assignments->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($assignments as $assignment) {
            try {
                $syncService->syncAssignment($assignment);
                $successCount++;
            } catch (\Throwable $e) {
                $failCount++;
                $this->newLine();
                $this->error("Failed to sync assignment ID {$assignment->id}: ".$e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Synchronization finished. Success: {$successCount}, Failed: {$failCount}.");

        return self::SUCCESS;
    }
}
