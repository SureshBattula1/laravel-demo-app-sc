<?php

namespace App\Console\Commands;

use App\Models\FeeDue;
use App\Services\FeeNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendOverdueFeeNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:send-overdue-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send overdue fee notifications to students/parents';

    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(FeeNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending overdue fee notifications...');

        try {
            // Get all overdue fees
            $overdueFees = FeeDue::where(function($q) {
                $q->where('status', 'Overdue')
                  ->orWhere(function($q2) {
                      $q2->where('status', 'Pending')
                         ->where('due_date', '<', now());
                  });
            })->where('balance_amount', '>', 0)
              ->with(['student.user'])
              ->get();

            $sentCount = 0;

            foreach ($overdueFees as $feeDue) {
                try {
                    // Check if notification was already sent today
                    $todayNotifications = \App\Models\Notification::where('user_id', $feeDue->student->user_id)
                        ->where('type', 'Warning')
                        ->where('title', 'Overdue Fee Alert')
                        ->whereDate('created_at', today())
                        ->whereJsonContains('metadata->fee_due_id', $feeDue->id)
                        ->count();

                    if ($todayNotifications === 0) {
                        $this->notificationService->sendOverdueFeeAlert($feeDue);
                        $sentCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Error sending overdue notification for fee due', [
                        'fee_due_id' => $feeDue->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("Sent {$sentCount} overdue fee notifications.");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error sending overdue notifications: ' . $e->getMessage());
            Log::error('Error in SendOverdueFeeNotifications command', [
                'error' => $e->getMessage()
            ]);
            
            return Command::FAILURE;
        }
    }
}
