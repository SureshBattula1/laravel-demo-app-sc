<?php

namespace App\Services;

/**
 * WhatsApp bulk sends use the same `sms_bulk_recipients` table and seed/repair logic as SMS.
 */
class WhatsappBulkRecipientSeedService extends SmsBulkRecipientSeedService
{
}
