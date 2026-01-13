<?php

namespace App\Console\Commands;

use App\Models\FeeDue;
use App\Services\FeeNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:send-payment-reminders {--days-before=7 : Number of days before due date to send reminder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send payment reminders for upcoming due dates';

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
        $daysBefore = (int) $this->option('days-before');
        $this->info("Sending payment reminders for fees due in {$daysBefore} days...");

        try {
            $reminderDate = now()->addDays($daysBefore);

            // Get fees due within the reminder period
            $fees = FeeDue::where('status', '!=', 'Paid')
                ->where('balance_amount', '>', 0)
                ->whereBetween('due_date', [now()->format('Y-m-d'), $reminderDate->format('Y-m-d')])
                ->with(['student.user'])
                ->get();

            $sentCount = 0;

            foreach ($fees as $feeDue) {
                try {
                    // Check if reminder was already sent this week
                    $thisWeekNotifications = \App\Models\Notification::where('user_id', $feeDue->student->user_id)
                        ->where('type', 'Info')
                        ->where('title', 'Payment Reminder')
                        ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                        ->whereJsonContains('metadata->fee_due_id', $feeDue->id)
                        ->count();

                    if ($thisWeekNotifications === 0) {
                        $this->notificationService->sendPaymentReminder($feeDue);
                        $sentCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Error sending payment reminder for fee due', [
                        'fee_due_id' => $feeDue->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("Sent {$sentCount} payment reminders.");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error sending payment reminders: ' . $e->getMessage());
            Log::error('Error in SendPaymentReminders command', [
                'error' => $e->getMessage()
            ]);
            
            return Command::FAILURE;
        }
    }
}
