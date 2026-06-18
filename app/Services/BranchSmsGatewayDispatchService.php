<?php

namespace App\Services;

use App\Models\BranchSmsGatewayConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Sends SMS using branch-stored gateway credentials (Twilio, MSG91, Textlocal, Nexmo).
 */
class BranchSmsGatewayDispatchService
{
    /**
     * @return array<string, mixed>
     */
    public function send(int $branchId, string $provider, string $to, string $message): array
    {
        $row = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('provider', $provider)
            ->where('channel', BranchSmsGatewayConfig::CHANNEL_SMS)
            ->firstOrFail();

        $credentials = $row->credentials;
        $credStatus = $credentials['status'] ?? 'Inactive';
        if (! $row->is_active && $credStatus !== 'Active') {
            throw new \RuntimeException('This SMS provider is not active for this branch.');
        }

        return match ($provider) {
            BranchSmsGatewayConfig::PROVIDER_TWILIO => $this->sendTwilio($credentials, $to, $message),
            BranchSmsGatewayConfig::PROVIDER_MSG91 => $this->sendMsg91($credentials, $to, $message),
            BranchSmsGatewayConfig::PROVIDER_LOCAL_TEXT => $this->sendTextlocal($credentials, $to, $message),
            BranchSmsGatewayConfig::PROVIDER_NEXMO => $this->sendNexmo($credentials, $to, $message),
            default => throw new \InvalidArgumentException('Unknown SMS provider'),
        };
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function sendTwilio(array $c, string $to, string $message): array
    {
        try {
            $client = new TwilioClient($c['account_sid'], $c['auth_token']);
            $msg = $client->messages->create($to, [
                'from' => $c['from_number'],
                'body' => $message,
            ]);

            return [
                'provider' => 'twilio',
                'message_sid' => $msg->sid,
                'status' => $msg->status,
            ];
        } catch (TwilioException $e) {
            Log::warning('Twilio SMS send failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Twilio: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function sendMsg91(array $c, string $to, string $message): array
    {
        $authKey = $c['auth_key'] ?? '';
        $sender = $c['sender_id'] ?? '';
        if ($authKey === '' || $sender === '') {
            throw new \RuntimeException('MSG91 configuration is incomplete.');
        }

        $mobiles = preg_replace('/\D/', '', $to);
        if ($mobiles === '') {
            throw new \RuntimeException('Invalid destination number for MSG91.');
        }

        $response = Http::timeout(45)->get('https://api.msg91.com/api/sendhttp.php', [
            'authkey' => $authKey,
            'mobiles' => $mobiles,
            'message' => $message,
            'sender' => $sender,
            'route' => '4',
            'country' => '0',
        ]);

        $body = trim($response->body());
        if (! $response->successful()) {
            throw new \RuntimeException('MSG91 request failed: HTTP '.$response->status());
        }

        if (str_starts_with($body, 'Invalid') || str_contains(strtolower($body), 'error')) {
            throw new \RuntimeException('MSG91: '.$body);
        }

        return [
            'provider' => 'msg91',
            'request_id' => $body,
            'status' => 'sent',
        ];
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function sendTextlocal(array $c, string $to, string $message): array
    {
        $username = $c['username'] ?? '';
        $hash = $c['hashkey'] ?? '';
        $sender = $c['sender_id'] ?? '';
        if ($username === '' || $hash === '' || $sender === '') {
            throw new \RuntimeException('Textlocal configuration is incomplete.');
        }

        $numbers = preg_replace('/\s+/', '', $to);

        $response = Http::asForm()->timeout(45)->post('https://api.textlocal.in/send/', [
            'username' => $username,
            'hash' => $hash,
            'numbers' => $numbers,
            'message' => $message,
            'sender' => $sender,
        ]);

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('Textlocal: invalid response from API.');
        }
        if (! $response->successful() || ($json['status'] ?? '') !== 'success') {
            $err = $json['errors'][0]['message'] ?? ($json['errors'] ?? $response->body());

            throw new \RuntimeException('Textlocal: '.(is_string($err) ? $err : json_encode($err)));
        }

        return [
            'provider' => 'local_text',
            'batch_id' => $json['batch_id'] ?? null,
            'status' => 'sent',
        ];
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function sendNexmo(array $c, string $to, string $message): array
    {
        $apiKey = $c['api_key'] ?? '';
        $apiSecret = $c['api_secret'] ?? '';
        $from = $c['from_number'] ?? '';
        if ($apiKey === '' || $apiSecret === '' || $from === '') {
            throw new \RuntimeException('Nexmo configuration is incomplete.');
        }

        $response = Http::asForm()->timeout(45)->post('https://rest.nexmo.com/sms/json', [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'to' => $to,
            'from' => $from,
            'text' => $message,
        ]);

        $json = $response->json();
        if (! $response->successful()) {
            throw new \RuntimeException('Nexmo HTTP '.$response->status());
        }

        $messages = $json['messages'] ?? [];
        $first = $messages[0] ?? [];
        if (($first['status'] ?? '') !== '0') {
            $err = $first['error-text'] ?? json_encode($first);

            throw new \RuntimeException('Nexmo: '.$err);
        }

        return [
            'provider' => 'nexmo',
            'message_id' => $first['message-id'] ?? null,
            'status' => 'sent',
        ];
    }

    /**
     * Send WhatsApp via Twilio using branch credentials. Optional `whatsapp_from` in encrypted credentials;
     * otherwise falls back to `from_number` or config `twilio.whatsapp_from`.
     *
     * @return array<string, mixed>
     */
    public function sendWhatsApp(int $branchId, string $provider, string $to, string $message): array
    {
        if ($provider !== BranchSmsGatewayConfig::PROVIDER_TWILIO) {
            throw new \InvalidArgumentException('WhatsApp bulk supports Twilio only.');
        }

        $row = BranchSmsGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('provider', $provider)
            ->where('channel', BranchSmsGatewayConfig::CHANNEL_WHATSAPP)
            ->firstOrFail();

        $credentials = $row->credentials;
        $credStatus = $credentials['status'] ?? 'Inactive';
        if (! $row->is_active && $credStatus !== 'Active') {
            throw new \RuntimeException('This WhatsApp provider is not active for this branch.');
        }

        return $this->sendTwilioWhatsApp($credentials, $to, $message);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function sendTwilioWhatsApp(array $c, string $to, string $message): array
    {
        try {
            $client = new TwilioClient($c['account_sid'], $c['auth_token']);
            $fromRaw = $c['whatsapp_from'] ?? $c['from_number'] ?? '';
            if ($fromRaw === '') {
                $fromRaw = (string) config('twilio.whatsapp_from', '');
            }
            if ($fromRaw === '') {
                throw new \RuntimeException('WhatsApp sender is not configured (set whatsapp_from on Twilio credentials or twilio.whatsapp_from).');
            }
            $from = str_starts_with($fromRaw, 'whatsapp:')
                ? $fromRaw
                : 'whatsapp:'.trim((string) $fromRaw, ' ');
            $toNormalized = $this->normalizeWhatsAppTo($to);
            if ($toNormalized === '') {
                throw new \RuntimeException('Invalid WhatsApp destination.');
            }

            $params = [
                'from' => $from,
                'body' => $message,
            ];
            $callback = $this->whatsAppStatusCallbackUrl();
            if ($callback !== null) {
                $params['statusCallback'] = $callback;
            }

            $msg = $client->messages->create($toNormalized, $params);

            return [
                'provider' => 'twilio_whatsapp',
                'message_sid' => $msg->sid,
                'status' => $msg->status,
            ];
        } catch (TwilioException $e) {
            Log::warning('Twilio WhatsApp send failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Twilio WhatsApp: '.$e->getMessage(), 0, $e);
        }
    }

    protected function normalizeWhatsAppTo(string $raw): string
    {
        $n = preg_replace('/\s+/', '', trim($raw));
        if ($n === '') {
            return '';
        }
        if (str_starts_with($n, 'whatsapp:')) {
            return $n;
        }
        if (! str_starts_with($n, '+')) {
            $digits = preg_replace('/\D/', '', $n);
            $n = $digits !== '' ? '+'.$digits : $n;
        }

        return 'whatsapp:'.$n;
    }

    /**
     * Twilio rejects non-public callback URLs (e.g. http://localhost). Omit unless usable.
     */
    protected function whatsAppStatusCallbackUrl(): ?string
    {
        $configured = trim((string) config('twilio.whatsapp_status_callback_url', ''));
        $url = $configured !== ''
            ? $configured
            : rtrim((string) config('app.url', ''), '/').'/api/webhooks/twilio/whatsapp-status';

        if ($url === '' || ! $this->isTwilioCallableWebhookUrl($url)) {
            return null;
        }

        return $url;
    }

    protected function isTwilioCallableWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.local')) {
            return false;
        }

        return true;
    }
}
