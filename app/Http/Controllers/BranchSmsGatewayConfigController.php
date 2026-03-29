<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchSmsGatewayConfig;
use App\Services\BranchSmsGatewayDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchSmsGatewayConfigController extends Controller
{
    /**
     * List gateway configs for a branch (`channel=sms` | `channel=whatsapp`; secrets masked).
     */
    public function index(Request $request, int $branchId)
    {
        if (!$this->userCanAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $branch = Branch::query()->whereKey($branchId)->firstOrFail();
        $channel = $this->normalizeChannel($request, true);

        $rows = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('channel', $channel)
            ->get()
            ->keyBy('provider');

        $providers = [];
        foreach (BranchSmsGatewayConfig::PROVIDERS as $provider) {
            if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP && $provider !== BranchSmsGatewayConfig::PROVIDER_TWILIO) {
                $providers[$provider] = $this->emptyMasked($provider, $channel);

                continue;
            }
            $row = $rows->get($provider);
            $providers[$provider] = $row
                ? array_merge($this->maskForResponse($provider, $row->credentials, $channel), [
                    'is_active' => (bool) $row->is_active,
                ])
                : $this->emptyMasked($provider, $channel);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'channel' => $channel,
                'providers' => $providers,
            ],
        ]);
    }

    /**
     * Create or update gateway config for a branch + provider + channel.
     */
    public function update(Request $request, int $branchId)
    {
        if (!$this->userCanAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        Branch::query()->whereKey($branchId)->firstOrFail();

        $channel = $this->normalizeChannel($request, false);

        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:' . implode(',', BranchSmsGatewayConfig::PROVIDERS),
            'channel' => 'nullable|string|in:' . BranchSmsGatewayConfig::CHANNEL_SMS . ',' . BranchSmsGatewayConfig::CHANNEL_WHATSAPP,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider = $request->input('provider');
        if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP && $provider !== BranchSmsGatewayConfig::PROVIDER_TWILIO) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp channel only supports Twilio.',
            ], 422);
        }

        $rules = $this->validationRulesForProvider($provider, $channel);
        $credValidator = Validator::make($request->all(), $rules);

        if ($credValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $credValidator->errors(),
            ], 422);
        }

        $incoming = $this->extractCredentialsFromRequest($provider, $request, $channel);
        $existing = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('provider', $provider)
            ->where('channel', $channel)
            ->first();

        $existingCreds = $existing ? $existing->credentials : [];
        $merged = $this->mergeCredentials($provider, $existingCreds, $incoming);

        $completeError = $this->validateCompleteForNew($provider, $merged, $existing !== null, $channel);
        if ($completeError !== null) {
            return response()->json([
                'success' => false,
                'message' => $completeError,
                'errors' => ['credentials' => [$completeError]],
            ], 422);
        }

        $isActive = ($merged['status'] ?? 'Inactive') === 'Active';

        BranchSmsGatewayConfig::query()->updateOrCreate(
            [
                'branch_id' => $branchId,
                'provider' => $provider,
                'channel' => $channel,
            ],
            ['credentials' => $merged, 'is_active' => $isActive]
        );

        if ($isActive) {
            BranchSmsGatewayConfig::query()
                ->where('branch_id', $branchId)
                ->where('channel', $channel)
                ->where('provider', '!=', $provider)
                ->get()
                ->each(function (BranchSmsGatewayConfig $other) {
                    $c = $other->credentials;
                    if (($c['status'] ?? '') === 'Active' || $other->is_active) {
                        $c['status'] = 'Inactive';
                        $other->update(['credentials' => $c, 'is_active' => false]);
                    }
                });
        }

        $fresh = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('provider', $provider)
            ->where('channel', $channel)
            ->firstOrFail();

        $savedMessage = $channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP
            ? 'WhatsApp gateway configuration saved'
            : 'SMS gateway configuration saved';

        return response()->json([
            'success' => true,
            'message' => $savedMessage,
            'data' => [
                'channel' => $channel,
                'provider' => $provider,
                'config' => array_merge(
                    $this->maskForResponse($provider, $fresh->credentials, $channel),
                    ['is_active' => (bool) $fresh->is_active]
                ),
            ],
        ]);
    }

    /**
     * Send a test SMS or WhatsApp using the saved configuration for this branch + provider + channel.
     */
    public function sendTest(Request $request, int $branchId, BranchSmsGatewayDispatchService $dispatch)
    {
        if (! $this->userCanAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $channel = $this->normalizeChannel($request, false);

        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:'.implode(',', BranchSmsGatewayConfig::PROVIDERS),
            'channel' => 'nullable|string|in:' . BranchSmsGatewayConfig::CHANNEL_SMS . ',' . BranchSmsGatewayConfig::CHANNEL_WHATSAPP,
            'to' => 'required|string|min:8|max:32',
            'message' => 'required|string|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $provider = $request->input('provider');
        if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP && $provider !== BranchSmsGatewayConfig::PROVIDER_TWILIO) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp test only supports Twilio.',
            ], 422);
        }

        $row = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('provider', $provider)
            ->where('channel', $channel)
            ->first();

        if ($row === null) {
            return response()->json([
                'success' => false,
                'message' => $channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP
                    ? 'Save WhatsApp (Twilio) configuration before sending a test.'
                    : 'Save this provider\'s configuration before sending a test SMS.',
            ], 422);
        }

        $creds = $row->credentials;
        $credStatus = $creds['status'] ?? 'Inactive';
        if (! $row->is_active && $credStatus !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'Set this provider to Active in the form before sending.',
            ], 422);
        }

        try {
            if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP) {
                $data = $dispatch->sendWhatsApp(
                    $branchId,
                    $provider,
                    $request->input('to'),
                    $request->input('message')
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Test WhatsApp message sent successfully.',
                    'data' => $data,
                ]);
            }

            $data = $dispatch->send(
                $branchId,
                $provider,
                $request->input('to'),
                $request->input('message')
            );

            return response()->json([
                'success' => true,
                'message' => 'Test SMS sent successfully.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function normalizeChannel(Request $request, bool $fromQuery): string
    {
        $raw = $fromQuery
            ? (string) $request->query('channel', BranchSmsGatewayConfig::CHANNEL_SMS)
            : (string) $request->input('channel', BranchSmsGatewayConfig::CHANNEL_SMS);

        if (! in_array($raw, [BranchSmsGatewayConfig::CHANNEL_SMS, BranchSmsGatewayConfig::CHANNEL_WHATSAPP], true)) {
            return BranchSmsGatewayConfig::CHANNEL_SMS;
        }

        return $raw;
    }

    protected function userCanAccessBranch(Request $request, int $branchId): bool
    {
        $accessible = $this->getAccessibleBranchIds($request);
        if ($accessible === 'all') {
            return true;
        }
        if (!is_array($accessible)) {
            return false;
        }

        return in_array($branchId, $accessible, true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyMasked(string $provider, string $channel = BranchSmsGatewayConfig::CHANNEL_SMS): array
    {
        $base = ['configured' => false, 'status' => 'Inactive', 'is_active' => false];
        switch ($provider) {
            case BranchSmsGatewayConfig::PROVIDER_TWILIO:
                $twilio = [
                    'account_sid' => null,
                    'auth_token_set' => false,
                    'from_number' => null,
                ];
                if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP) {
                    $twilio['whatsapp_from'] = null;
                }

                return $base + $twilio;
            case BranchSmsGatewayConfig::PROVIDER_MSG91:
                return $base + [
                    'auth_key_set' => false,
                    'sender_id' => null,
                ];
            case BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT:
                return $base + [
                    'username' => null,
                    'hashkey_set' => false,
                    'sender_id' => null,
                ];
            case BranchSmsGatewayConfig::PROVIDER_NEXMO:
                return $base + [
                    'api_key' => null,
                    'api_secret_set' => false,
                    'from_number' => null,
                ];
            default:
                return $base;
        }
    }

    /**
     * @param  array<string, mixed>  $credentials  Decrypted credentials
     * @return array<string, mixed>
     */
    protected function maskForResponse(string $provider, array $credentials, string $channel = BranchSmsGatewayConfig::CHANNEL_SMS): array
    {
        $status = $credentials['status'] ?? 'Inactive';
        $out = ['configured' => true, 'status' => $status];

        switch ($provider) {
            case BranchSmsGatewayConfig::PROVIDER_TWILIO:
                $out['account_sid'] = $this->maskSid($credentials['account_sid'] ?? '');
                $out['auth_token_set'] = !empty($credentials['auth_token']);
                $out['from_number'] = $this->maskPhone($credentials['from_number'] ?? '');
                if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP) {
                    $out['whatsapp_from'] = $this->maskPhone($credentials['whatsapp_from'] ?? '');
                }

                return $out;
            case BranchSmsGatewayConfig::PROVIDER_MSG91:
                $out['auth_key_set'] = !empty($credentials['auth_key']);
                $out['sender_id'] = $credentials['sender_id'] ?? null;

                return $out;
            case BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT:
                $out['username'] = $credentials['username'] ?? null;
                $out['hashkey_set'] = !empty($credentials['hashkey']);
                $out['sender_id'] = $credentials['sender_id'] ?? null;

                return $out;
            case BranchSmsGatewayConfig::PROVIDER_NEXMO:
                $out['api_key'] = $this->maskSid($credentials['api_key'] ?? '');
                $out['api_secret_set'] = !empty($credentials['api_secret']);
                $out['from_number'] = $this->maskPhone($credentials['from_number'] ?? '');

                return $out;
            default:
                return $out;
        }
    }

    protected function maskSid(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $len = strlen($value);
        if ($len <= 4) {
            return '****';
        }

        return str_repeat('*', min(12, $len - 4)) . substr($value, -4);
    }

    protected function maskPhone(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $len = strlen($value);
        if ($len <= 4) {
            return '****';
        }

        return substr($value, 0, 2) . str_repeat('*', max(0, $len - 6)) . substr($value, -4);
    }

    /**
     * @return array<string, string>
     */
    protected function validationRulesForProvider(string $provider, string $channel = BranchSmsGatewayConfig::CHANNEL_SMS): array
    {
        $status = 'required|string|in:Active,Inactive';

        switch ($provider) {
            case BranchSmsGatewayConfig::PROVIDER_TWILIO:
                if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP) {
                    return [
                        'account_sid' => 'nullable|string|max:255',
                        'auth_token' => 'nullable|string|max:512',
                        'from_number' => 'nullable|string|max:32',
                        'whatsapp_from' => 'nullable|string|max:64',
                        'status' => $status,
                    ];
                }

                return [
                    'account_sid' => 'nullable|string|max:255',
                    'auth_token' => 'nullable|string|max:512',
                    'from_number' => 'nullable|string|max:32',
                    'status' => $status,
                ];
            case BranchSmsGatewayConfig::PROVIDER_MSG91:
                return [
                    'auth_key' => 'nullable|string|max:512',
                    'sender_id' => 'nullable|string|max:32',
                    'status' => $status,
                ];
            case BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT:
                return [
                    'username' => 'nullable|string|max:255',
                    'hashkey' => 'nullable|string|max:512',
                    'sender_id' => 'nullable|string|max:32',
                    'status' => $status,
                ];
            case BranchSmsGatewayConfig::PROVIDER_NEXMO:
                return [
                    'api_key' => 'nullable|string|max:255',
                    'api_secret' => 'nullable|string|max:512',
                    'from_number' => 'nullable|string|max:32',
                    'status' => $status,
                ];
            default:
                return ['status' => $status];
        }
    }

    /**
     * On create, required fields must be present (validated separately).
     *
     * @return array<string, mixed>
     */
    protected function extractCredentialsFromRequest(string $provider, Request $request, string $channel = BranchSmsGatewayConfig::CHANNEL_SMS): array
    {
        switch ($provider) {
            case BranchSmsGatewayConfig::PROVIDER_TWILIO:
                $out = [
                    'account_sid' => $request->input('account_sid'),
                    'auth_token' => $request->input('auth_token'),
                    'from_number' => $request->input('from_number'),
                    'status' => $request->input('status'),
                ];
                if ($channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP) {
                    $out['whatsapp_from'] = $request->input('whatsapp_from');
                }

                return $out;
            case BranchSmsGatewayConfig::PROVIDER_MSG91:
                return [
                    'auth_key' => $request->input('auth_key'),
                    'sender_id' => $request->input('sender_id'),
                    'status' => $request->input('status'),
                ];
            case BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT:
                return [
                    'username' => $request->input('username'),
                    'hashkey' => $request->input('hashkey'),
                    'sender_id' => $request->input('sender_id'),
                    'status' => $request->input('status'),
                ];
            case BranchSmsGatewayConfig::PROVIDER_NEXMO:
                return [
                    'api_key' => $request->input('api_key'),
                    'api_secret' => $request->input('api_secret'),
                    'from_number' => $request->input('from_number'),
                    'status' => $request->input('status'),
                ];
            default:
                return [];
        }
    }

    /**
     * Merge incoming credentials with existing; empty strings for secrets keep previous values.
     *
     * @param  array<string, mixed>  $existing  Decrypted
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeCredentials(string $provider, array $existing, array $incoming): array
    {
        $secretKeys = match ($provider) {
            BranchSmsGatewayConfig::PROVIDER_TWILIO => ['auth_token'],
            BranchSmsGatewayConfig::PROVIDER_MSG91 => ['auth_key'],
            BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT => ['hashkey'],
            BranchSmsGatewayConfig::PROVIDER_NEXMO => ['api_secret'],
            default => [],
        };

        $out = $existing;

        foreach ($incoming as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($value === '' && in_array($key, $secretKeys, true) && !empty($existing[$key])) {
                continue;
            }
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        if (array_key_exists('status', $incoming) && $incoming['status'] !== null) {
            $out['status'] = $incoming['status'];
        }

        return $out;
    }

    /**
     * First-time save must include all required fields; updates may omit unchanged secrets (handled in merge).
     */
    protected function validateCompleteForNew(string $provider, array $merged, bool $isUpdate, string $channel = BranchSmsGatewayConfig::CHANNEL_SMS): ?string
    {
        if ($isUpdate) {
            return null;
        }

        if ($provider === BranchSmsGatewayConfig::PROVIDER_TWILIO && $channel === BranchSmsGatewayConfig::CHANNEL_WHATSAPP) {
            $checks = [
                'account_sid' => 'Twilio Account SID',
                'auth_token' => 'Authentication Token',
                'whatsapp_from' => 'WhatsApp sender number (Twilio)',
                'status' => 'Status',
            ];
            foreach ($checks as $field => $label) {
                if (empty($merged[$field])) {
                    return "{$label} is required when saving a new configuration.";
                }
            }

            return null;
        }

        $checks = match ($provider) {
            BranchSmsGatewayConfig::PROVIDER_TWILIO => [
                'account_sid' => 'Twilio Account SID',
                'auth_token' => 'Authentication Token',
                'from_number' => 'Registered Phone Number',
                'status' => 'Status',
            ],
            BranchSmsGatewayConfig::PROVIDER_MSG91 => [
                'auth_key' => 'Auth Key',
                'sender_id' => 'Sender ID',
                'status' => 'Status',
            ],
            BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT => [
                'username' => 'Username',
                'hashkey' => 'Hash key',
                'sender_id' => 'Sender ID',
                'status' => 'Status',
            ],
            BranchSmsGatewayConfig::PROVIDER_NEXMO => [
                'api_key' => 'Nexmo API Key',
                'api_secret' => 'Nexmo API Secret',
                'from_number' => 'Registered / From Number',
                'status' => 'Status',
            ],
            default => [],
        };

        foreach ($checks as $field => $label) {
            if (empty($merged[$field])) {
                return "{$label} is required when saving a new configuration.";
            }
        }

        return null;
    }
}
