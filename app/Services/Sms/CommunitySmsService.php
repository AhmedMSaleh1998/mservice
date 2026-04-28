<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\PhoneNumberNormalizer;
use Modules\Core\Models\SmsMessage;
use RuntimeException;

class CommunitySmsService
{
    private const OTP_TEMPLATE = 'Your verification code is :code';

    public function isEnabled(): bool
    {
        return (bool) config('services.community_sms.enabled')
            && filled($this->endpoint())
            && filled($this->username())
            && filled($this->password())
            && filled($this->sender());
    }

    public function send(string $phone, string $message, array $options = []): array
    {
        $this->ensureConfigured();

        $messageId = (string) ($options['message_id'] ?? Str::uuid());
        $sender = (string) ($options['sender'] ?? $this->sender());
        $receiver = $this->normalizeReceiver((string) ($options['receiver'] ?? $phone));

        $smsMessage = SmsMessage::query()->create([
            'provider' => 'community_sms',
            'type' => (string) ($options['type'] ?? 'generic'),
            'message_id' => $messageId,
            'sender' => $sender,
            'receiver' => $receiver,
            'message' => $message,
            'status' => 'pending',
            'metadata' => $options['metadata'] ?? null,
        ]);

        $payload = array_filter([
            'UserName' => $this->username(),
            'Password' => $this->password(),
            'SMSText' => $message,
            'SMSLang' => (string) ($options['lang'] ?? config('services.community_sms.lang', 'a')),
            'SMSSender' => $sender,
            'SMSReceiver' => $receiver,
            'SMSID' => $messageId,
            'DLRURL' => $options['dlr_url'] ?? $this->resolveDlrUrl(),
        ], static fn ($value): bool => ! is_null($value) && $value !== '');

        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.community_sms.timeout', 20))
            ->connectTimeout((int) config('services.community_sms.connect_timeout', 5))
            ->post($this->endpoint(), $payload);

        if (! $response->successful()) {
            $smsMessage->update([
                'status' => 'failed',
                'response_status_code' => $response->status(),
                'response_body' => $response->body(),
                'failed_at' => now(),
                'last_status_at' => now(),
            ]);

            Log::warning('Community SMS request failed', [
                'http_status' => $response->status(),
                'request' => Arr::except($payload, ['Password']),
                'response_body' => $response->body(),
            ]);

            $response->throw();
        }

        $statusCode = $this->extractStatusCode($response);

        if ($statusCode !== 0) {
            $smsMessage->update([
                'status' => 'failed',
                'provider_status' => (string) $statusCode,
                'response_status_code' => $statusCode,
                'response_body' => $response->body(),
                'failed_at' => now(),
                'last_status_at' => now(),
            ]);

            Log::warning('Community SMS provider rejected message', [
                'status_code' => $statusCode,
                'request' => Arr::except($payload, ['Password']),
                'response_body' => $response->body(),
            ]);

            throw new RuntimeException("Community SMS rejected message with status code [{$statusCode}].");
        }

        $smsMessage->update([
            'status' => 'accepted',
            'provider_status' => (string) $statusCode,
            'response_status_code' => $statusCode,
            'response_body' => $response->body(),
            'sent_at' => now(),
            'last_status_at' => now(),
        ]);

        return [
            'provider' => 'community_sms',
            'status_code' => $statusCode,
            'message_id' => $messageId,
            'receiver' => $receiver,
            'response_body' => $response->body(),
        ];
    }

    public function sendOtp(string $phone, string $code, array $options = []): array
    {
        $template = (string) ($options['template'] ?? self::OTP_TEMPLATE);

        return $this->send($phone, str_replace(':code', $code, $template), [
            'message_id' => $options['message_id'] ?? null,
            'lang' => $options['lang'] ?? config('services.community_sms.otp_lang', config('services.community_sms.lang', 'a')),
            'sender' => $options['sender'] ?? null,
            'receiver' => $options['receiver'] ?? null,
            'dlr_url' => $options['dlr_url'] ?? config('services.community_sms.otp_dlr_url', config('services.community_sms.dlr_url')),
            'type' => 'otp',
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    private function ensureConfigured(): void
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Community SMS is not configured.');
        }
    }

    private function endpoint(): string
    {
        return (string) config('services.community_sms.endpoint', '');
    }

    private function username(): string
    {
        return (string) config('services.community_sms.username', '');
    }

    private function password(): string
    {
        return (string) config('services.community_sms.password', '');
    }

    private function sender(): string
    {
        return (string) config('services.community_sms.sender', '');
    }

    private function resolveDlrUrl(): ?string
    {
        $configuredUrl = (string) config('services.community_sms.dlr_url', '');

        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        return route('api.sms.community.dlr');
    }

    private function normalizeReceiver(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            throw new RuntimeException('A valid SMS receiver is required.');
        }

        if (! (bool) config('services.community_sms.normalize_receivers', true)) {
            return $digits;
        }

        return PhoneNumberNormalizer::normalize($digits);
    }

    private function extractStatusCode(Response $response): int
    {
        $decoded = $response->json();

        if (is_int($decoded) || (is_string($decoded) && is_numeric($decoded))) {
            return (int) $decoded;
        }

        $body = trim($response->body());
        $plainBody = trim(strip_tags($body));

        if ($plainBody !== '' && is_numeric($plainBody)) {
            return (int) $plainBody;
        }

        throw new RuntimeException('Unexpected response received from Community SMS.');
    }
}
