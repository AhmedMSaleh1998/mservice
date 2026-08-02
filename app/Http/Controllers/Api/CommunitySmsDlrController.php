<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\SmsMessage;

class CommunitySmsDlrController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        $messageId = $this->firstFilledValue($payload, [
            'userSMSId',
            'SMSID',
            'smsid',
            'message_id',
            'messageId',
            'MessageID',
        ]);

        $providerStatus = $this->firstFilledValue($payload, [
            'dlrResponseStatus',
            'DLRResponseStatus',
            'deliveryStatus',
            'DeliveryStatus',
            'status',
            'Status',
        ]);

        if (blank($messageId)) {
            Log::warning('Community SMS DLR received without message id', [
                'payload' => $payload,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'SMS message id is required.',
            ], 422);
        }

        $message = SmsMessage::query()->firstOrNew([
            'message_id' => (string) $messageId,
        ]);

        $normalizedStatus = $this->normalizeStatus($providerStatus);

        $message->fill([
            'provider' => 'community_sms',
            'message' => $message->message ?: (string) ($this->firstFilledValue($payload, [
                'SMSText',
                'smsText',
                'message',
                'Message',
            ]) ?? '[DLR only]'),
            'sender' => $message->sender ?: $this->firstFilledValue($payload, [
                'SMSSender',
                'smsSender',
                'sender',
                'Sender',
            ]),
            'receiver' => $message->receiver ?: $this->firstFilledValue($payload, [
                'SMSReceiver',
                'smsReceiver',
                'receiver',
                'Receiver',
                'mobile',
                'Mobile',
            ]),
            'status' => $normalizedStatus,
            'provider_status' => $providerStatus !== null ? (string) $providerStatus : null,
            'dlr_payload' => $payload,
            'last_status_at' => now(),
            'delivered_at' => $normalizedStatus === 'delivered' ? now() : $message->delivered_at,
            'failed_at' => $normalizedStatus === 'failed' ? now() : $message->failed_at,
        ]);

        $message->save();

        return response()->json([
            'status' => true,
            'message' => 'SMS delivery report received.',
        ]);
    }

    private function payload(Request $request): array
    {
        $payload = $request->all();

        if ($payload !== []) {
            return $payload;
        }

        $json = $request->json()->all();

        if ($json !== []) {
            return $json;
        }

        parse_str((string) $request->getContent(), $formPayload);

        return is_array($formPayload) ? $formPayload : [];
    }

    private function firstFilledValue(array $payload, array $keys): string|int|null
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? $value : null;
            }
        }

        return null;
    }

    private function normalizeStatus(string|int|null $providerStatus): string
    {
        if ($providerStatus === null || $providerStatus === '') {
            return 'reported';
        }

        $normalized = strtolower(trim((string) $providerStatus));

        return match (true) {
            in_array($normalized, ['delivered', 'deliverd', 'deliverysuccess', 'success', 'received'], true) => 'delivered',
            in_array($normalized, ['failed', 'undelivered', 'rejected', 'expired', 'not_delivered'], true) => 'failed',
            in_array($normalized, ['accepted', 'submitted', 'sent', 'queued', 'pending'], true) => 'accepted',
            default => 'reported',
        };
    }
}
