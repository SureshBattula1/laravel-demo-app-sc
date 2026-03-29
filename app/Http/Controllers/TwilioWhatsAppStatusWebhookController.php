<?php

namespace App\Http\Controllers;

use App\Models\SmsBulkQueue;
use App\Models\SmsBulkRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwilioWhatsAppStatusWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $sid = trim((string) $request->input('MessageSid', ''));
        $rawStatus = strtolower(trim((string) $request->input('MessageStatus', '')));
        $errorCode = trim((string) $request->input('ErrorCode', ''));
        $errorMessage = trim((string) $request->input('ErrorMessage', ''));

        if ($sid === '' || $rawStatus === '') {
            return response()->json(['success' => true, 'ignored' => true]);
        }

        $status = $this->mapTwilioStatus($rawStatus);
        if ($status === null) {
            return response()->json(['success' => true, 'ignored' => true, 'status' => $rawStatus]);
        }

        $rows = SmsBulkRecipient::query()
            ->whereHas('queue', function ($q) {
                $q->where('channel', 'whatsapp');
            })
            ->where('provider_meta->provider', 'twilio_whatsapp')
            ->where('provider_meta->message_sid', $sid)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'updated' => 0]);
        }

        $queueIds = [];
        foreach ($rows as $row) {
            $meta = is_array($row->provider_meta) ? $row->provider_meta : [];
            $meta['status'] = $rawStatus;
            if ($errorCode !== '') {
                $meta['error_code'] = $errorCode;
            }
            if ($errorMessage !== '') {
                $meta['error_message'] = $errorMessage;
            }

            $row->status = $status;
            $row->error_message = $status === SmsBulkRecipient::STATUS_FAILED
                ? ($errorMessage !== '' ? $errorMessage : ($errorCode !== '' ? "Twilio error {$errorCode}" : "Twilio status {$rawStatus}"))
                : null;
            $row->provider_meta = $meta;
            $row->sent_at = $status === SmsBulkRecipient::STATUS_SENT ? now() : null;
            $row->save();

            $queueIds[] = (int) $row->sms_bulk_queue_id;
        }

        foreach (array_unique($queueIds) as $queueId) {
            SmsBulkQueue::maybeMarkCompleted($queueId);
        }

        return response()->json([
            'success' => true,
            'updated' => $rows->count(),
            'status' => $rawStatus,
        ]);
    }

    private function mapTwilioStatus(string $rawStatus): ?string
    {
        return match ($rawStatus) {
            'sent', 'delivered', 'read', 'received' => SmsBulkRecipient::STATUS_SENT,
            'failed', 'undelivered', 'canceled', 'cancelled' => SmsBulkRecipient::STATUS_FAILED,
            'queued', 'accepted', 'scheduled', 'sending' => SmsBulkRecipient::STATUS_PENDING,
            default => null,
        };
    }
}

