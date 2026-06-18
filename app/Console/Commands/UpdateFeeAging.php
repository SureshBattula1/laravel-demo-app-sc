<?php

namespace App\Console\Commands;

use App\Models\FeeDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateFeeAging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:update-aging';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update overdue days for all fee dues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating fee aging...');

        try {
            $dues = FeeDue::where('status', '!=', 'Paid')
                ->where('balance_amount', '>', 0)
                ->get();

            $updatedCount = 0;

            foreach ($dues as $due) {
                if ($due->due_date) {
                    $dueDate = Carbon::parse($due->due_date);
                    $overdueDays = max(0, now()->diffInDays($dueDate, false) * -1);
                    
                    // Update overdue days
                    $due->update(['overdue_days' => $overdueDays]);

                    // Update status to Overdue if past due date
                    if ($overdueDays > 0 && $due->status !== 'Overdue' && $due->status !== 'CarriedForward') {
                        $due->update(['status' => 'Overdue']);
                    }

                    $updatedCount++;
                }
            }

            $this->info("Updated aging for {$updatedCount} fee dues.");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error updating fee aging: ' . $e->getMessage());
            Log::error('Error in UpdateFeeAging command', [
                'error' => $e->getMessage()
            ]);
            
            return Command::FAILURE;
        }
    }
}
