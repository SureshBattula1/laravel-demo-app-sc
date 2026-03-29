<?php

namespace App\Http\Controllers;

/**
 * Delivery log for WhatsApp bulk batches (same storage as SMS, filtered by channel=whatsapp).
 */
class WhatsAppBulkQueueLogController extends SmsBulkQueueLogController
{
    /** @var 'whatsapp' */
    protected string $channelFilter = 'whatsapp';
}
