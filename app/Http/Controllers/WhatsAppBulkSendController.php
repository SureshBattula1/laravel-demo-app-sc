<?php

namespace App\Http\Controllers;

use App\Services\BranchSmsActiveProviderResolver;

/**
 * Same audience + template flow as {@see SmsBulkSendController}; uses Twilio WhatsApp and channel=whatsapp.
 */
class WhatsAppBulkSendController extends SmsBulkSendController
{
    /** @var 'whatsapp' */
    protected string $bulkChannel = 'whatsapp';

    protected function resolveBulkProvider(BranchSmsActiveProviderResolver $resolver, int $branchId): ?string
    {
        return $resolver->resolveWhatsApp($branchId);
    }

    protected function bulkSuccessMessage(): string
    {
        return 'Bulk WhatsApp queued.';
    }

    protected function providerMissingMessage(): string
    {
        return 'No active Twilio gateway for WhatsApp for this branch. Configure Twilio under Account Management -> WhatsApp settings -> Configurations.';
    }
}
