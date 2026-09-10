<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Services\AssignmentNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchAssignmentNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $assignmentId,
        public string $event = 'created'
    ) {}

    public static function dispatchFor(int $assignmentId, string $event = 'created'): mixed
    {
        return static::dispatchSync($assignmentId, $event);
    }

    public function handle(AssignmentNotificationService $service): void
    {
        $assignment = Assignment::withoutTenantScope()->with('recipients')->find($this->assignmentId);
        if (!$assignment) {
            Log::warning('Assignment notification job skipped; assignment missing', [
                'assignment_id' => $this->assignmentId,
            ]);
            return;
        }

        $service->fanOut($assignment, $this->event);
    }
}
