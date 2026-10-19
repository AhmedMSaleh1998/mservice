<?php

namespace Modules\Notifications\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class FcmService
{
    /** FCM accepts at most 500 registration tokens per multicast request. */
    private const MULTICAST_LIMIT = 500;

    public function isEnabled(): bool
    {
        return (bool) config('services.fcm.enabled');
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, string|int>  $data
     * @return array{sent: int, failed: int, invalid_tokens: array<int, string>}
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return $result;
        }

        // Resolved per send so a missing service account never breaks app boot.
        $messaging = app(Messaging::class);
        $message = CloudMessage::fromArray([
            'notification' => ['title' => $title, 'body' => $body],
            // FCM data values must be strings.
            'data' => array_map('strval', $data),
            'android' => ['priority' => 'high', 'notification' => ['sound' => 'default']],
            'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
        ]);

        foreach (array_chunk($tokens, self::MULTICAST_LIMIT) as $chunk) {
            $report = $messaging->sendMulticast($message, $chunk);

            $result['sent'] += $report->successes()->count();
            $result['failed'] += $report->failures()->count();
            array_push($result['invalid_tokens'], ...$report->invalidTokens(), ...$report->unknownTokens());
        }

        return $result;
    }
}
