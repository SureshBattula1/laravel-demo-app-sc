<?php

namespace App\Services;

use App\Models\BranchSmsGatewayConfig;

/**
 * Picks the first SMS provider for a branch that is marked active ({@see BranchSmsGatewayConfig::is_active})
 * or (legacy) has credentials status Active. Order follows {@see BranchSmsGatewayConfig::PROVIDERS}.
 */
class BranchSmsActiveProviderResolver
{
    public function resolve(int $branchId): ?string
    {
        $rows = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('channel', BranchSmsGatewayConfig::CHANNEL_SMS)
            ->get()
            ->keyBy('provider');

        foreach (BranchSmsGatewayConfig::PROVIDERS as $provider) {
            $row = $rows->get($provider);
            if ($row === null) {
                continue;
            }
            if ($row->is_active) {
                return $provider;
            }
            $creds = $row->credentials;
            if (($creds['status'] ?? 'Inactive') === 'Active') {
                return $provider;
            }
        }

        return null;
    }

    /**
     * WhatsApp bulk uses Twilio only; requires an active Twilio row for the branch.
     */
    public function resolveWhatsApp(int $branchId): ?string
    {
        $row = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('provider', BranchSmsGatewayConfig::PROVIDER_TWILIO)
            ->where('channel', BranchSmsGatewayConfig::CHANNEL_WHATSAPP)
            ->first();

        if ($row === null) {
            return null;
        }

        if ($row->is_active) {
            return BranchSmsGatewayConfig::PROVIDER_TWILIO;
        }

        $creds = $row->credentials;

        return ($creds['status'] ?? 'Inactive') === 'Active'
            ? BranchSmsGatewayConfig::PROVIDER_TWILIO
            : null;
    }
}
