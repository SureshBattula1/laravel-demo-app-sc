<?php

namespace App\Jobs;

use App\Models\SmsBulkQueue;
use App\Models\SmsBulkRecipient;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\BranchSmsGatewayDispatchService;
use App\Services\SmsTemplateTagContextFactory;
use App\Services\SmsTemplateTagRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBulkSmsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Dispatch bulk chunk; uses sync execution when config('sms.bulk.dispatch_synchronously') is true.
     *
     * @param  list<int>  $recipientIds
     * @return mixed PendingDispatch or job result when run synchronously
     */
    public static function dispatchChunk(
        int $branchId,
        string $provider,
        string $recipientType,
        array $recipientIds,
        string $bodyTemplate,
        ?int $queueId = null
    ): mixed {
        if (config('sms.bulk.dispatch_synchronously')) {
            return static::dispatchSync($branchId, $provider, $recipientType, $recipientIds, $bodyTemplate, $queueId);
        }

        return static::dispatch($branchId, $provider, $recipientType, $recipientIds, $bodyTemplate, $queueId);
    }

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public int $branchId,
        public string $provider,
        public string $recipientType,
        public array $recipientIds,
        public string $bodyTemplate,
        public ?int $queueId = null
    ) {}

    public function handle(
        BranchSmsGatewayDispatchService $dispatch,
        SmsTemplateTagRenderer $renderer,
        SmsTemplateTagContextFactory $contextFactory
    ): void {
        if ($this->queueId !== null) {
            SmsBulkQueue::query()
                ->whereKey($this->queueId)
                ->where('status', 'queued')
                ->update(['status' => 'processing']);
        }

        foreach ($this->recipientIds as $recipientId) {
            try {
                if ($this->recipientType === 'student') {
                    $model = Student::query()
                        ->where('branch_id', $this->branchId)
                        ->whereKey($recipientId)
                        ->with('user')
                        ->first();
                } else {
                    $model = Teacher::query()
                        ->where('branch_id', $this->branchId)
                        ->whereKey($recipientId)
                        ->with('user')
                        ->first();
                }

                if ($model === null) {
                    $this->markRecipient($recipientId, 'skipped', 'Recipient record not found.', null);

                    continue;
                }

                $context = $this->recipientType === 'student'
                    ? $contextFactory->buildForStudent($model)
                    : $contextFactory->buildForTeacher($model);

                $body = $renderer->render($this->bodyTemplate, $context);
                $to = $context['mobile'] ?? '';
                if ($to === '') {
                    Log::warning('Bulk SMS skipped: no mobile', [
                        'branch_id' => $this->branchId,
                        'recipient_type' => $this->recipientType,
                        'id' => $recipientId,
                    ]);
                    $this->markRecipient($recipientId, 'skipped', 'No mobile number on file.', null);

                    continue;
                }

                $result = $dispatch->send($this->branchId, $this->provider, $to, $body);
                $this->markRecipient($recipientId, 'sent', null, $result);
            } catch (\Throwable $e) {
                Log::warning('Bulk SMS send failed for recipient', [
                    'branch_id' => $this->branchId,
                    'recipient_type' => $this->recipientType,
                    'id' => $recipientId,
                    'error' => $e->getMessage(),
                ]);
                $this->markRecipient($recipientId, 'failed', $e->getMessage(), null);
            }
        }

        if ($this->queueId !== null) {
            SmsBulkQueue::maybeMarkCompleted($this->queueId);
        }
    }

    /**
     * @param  array<string, mixed>|null  $providerMeta
     */
    private function markRecipient(int $recipientId, string $status, ?string $errorMessage, ?array $providerMeta): void
    {
        if ($this->queueId === null) {
            return;
        }

        $update = [
            'status' => $status,
            'error_message' => $errorMessage,
            'provider_meta' => $providerMeta,
            'sent_at' => $status === 'sent' ? now() : null,
        ];

        SmsBulkRecipient::query()
            ->where('sms_bulk_queue_id', $this->queueId)
            ->where('recipient_type', $this->recipientType)
            ->where('recipient_id', $recipientId)
            ->update($update);
    }
}
