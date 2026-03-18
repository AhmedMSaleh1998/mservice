<?php

namespace Modules\Core\Services;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Ads\Models\AdRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Courses\Models\CourseBooking;

class OrderService
{
    public function sync(Model $orderable, array $attributes = []): Order
    {
        $order = $orderable->relationLoaded('order')
            ? $orderable->getRelation('order')
            : $orderable->order()->first();

        if (! $order) {
            $order = $orderable->order()->make();
        }

        $order->user_id = $attributes['user_id'] ?? $order->user_id ?? data_get($orderable, 'user_id');
        $order->amount = $this->formatAmount($attributes['amount'] ?? $order->amount ?? data_get($orderable, 'total_amount', 0));
        $order->currency = $attributes['currency'] ?? $order->currency ?? (string) config('checkout.currency', 'EGP');

        foreach ([
            'status',
            'payment_method',
            'provider',
            'merchant_ref_num',
            'gateway_reference',
            'gateway_status',
            'checkout_url',
            'payload',
            'payment_started_at',
            'payment_last_synced_at',
            'paid_at',
        ] as $field) {
            if (array_key_exists($field, $attributes)) {
                $order->{$field} = $attributes[$field];
            }
        }

        $order->save();

        $freshOrder = $order->fresh(['orderable', 'user']);
        $orderable->setRelation('order', $freshOrder);

        return $freshOrder;
    }

    public function availablePaymentMethods()
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function buildPaymentState(Order $order): array
    {
        return [
            'selected_method' => $order->payment_method,
            'gateway' => $order->provider === 'fawry' ? 'fawry' : null,
            'gateway_status' => $order->gateway_status,
            'merchant_ref_num' => $order->merchant_ref_num,
            'fawry_reference_number' => $order->gateway_reference,
            'payment_url' => $order->checkout_url,
            'started_at' => optional($order->payment_started_at)->format('Y-m-d H:i:s'),
            'paid_at' => optional($order->paid_at)->format('Y-m-d H:i:s'),
            'last_synced_at' => optional($order->payment_last_synced_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function startCheckout(Order $order, string $paymentMethod): array
    {
        $checkout = [
            'mode' => config('checkout.mock_enabled', true) ? 'mock' : 'manual',
            'provider' => config('checkout.mock_enabled', true) ? 'mock' : 'manual',
            'status' => 'checkout_pending',
            'payment_method' => $paymentMethod,
            'reference' => $this->buildMerchantRefNum($order),
            'payment_url' => null,
            'instructions' => __('Mock checkout is enabled. Use the confirm payment endpoint to mark this request as paid.'),
        ];

        $this->sync($order->orderable, [
            'status' => 'checkout_pending',
            'payment_method' => $paymentMethod,
            'provider' => (string) $checkout['provider'],
            'merchant_ref_num' => (string) $checkout['reference'],
            'checkout_url' => null,
            'payment_started_at' => now(),
            'payment_last_synced_at' => now(),
        ]);

        return $checkout;
    }

    public function reusableFawryCheckout(Order $order): ?array
    {
        if (
            $order->payment_method !== 'fawry'
            || blank($order->checkout_url)
            || $order->status === 'paid_successfully'
            || in_array((string) $order->gateway_status, ['UNPAID', 'FAILED', 'EXPIRED', 'CANCELED'], true)
        ) {
            return null;
        }

        return [
            'mode' => 'redirect',
            'provider' => 'fawry',
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'reference' => $order->merchant_ref_num,
            'gateway_reference' => $order->gateway_reference,
            'payment_url' => $order->checkout_url,
            'instructions' => __('Open the payment URL to complete your payment via Fawry.'),
        ];
    }

    public function recordFawryCheckout(Order $order, array $checkout): Order
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['charge_request'] = $checkout['charge_request'] ?? [];
        $payload['charge_response'] = $checkout['charge_response'] ?? [];

        return $this->sync($order->orderable, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => (string) ($checkout['merchant_ref_num'] ?? $order->merchant_ref_num),
            'gateway_reference' => (string) ($checkout['reference_number'] ?? $order->gateway_reference),
            'gateway_status' => (string) ($checkout['gateway_status'] ?? 'NEW'),
            'checkout_url' => (string) ($checkout['payment_url'] ?? $order->checkout_url),
            'payload' => $payload,
            'payment_started_at' => now(),
            'payment_last_synced_at' => now(),
        ]);
    }

    public function markPaid(Order $order, string $paymentMethod): Order
    {
        $order = $this->sync($order->orderable, [
            'status' => 'paid_successfully',
            'payment_method' => $paymentMethod,
            'provider' => $paymentMethod === 'fawry' ? 'fawry' : 'manual',
            'gateway_status' => 'PAID',
            'paid_at' => $order->paid_at ?: now(),
            'payment_last_synced_at' => now(),
        ]);

        $this->syncOrderableStatus($order, 'paid_successfully');

        return $order;
    }

    public function confirmPayment(Order $order): Order
    {
        return $this->markPaid($order, (string) $order->payment_method);
    }

    public function applyFawryPaymentUpdate(Order $order, array $payload, string $source): Order
    {
        $storedPayload = is_array($order->payload) ? $order->payload : [];
        $storedPayload[$source] = $payload;

        $orderStatus = strtoupper((string) data_get($payload, 'orderStatus', $order->gateway_status));
        $gatewayReference = (string) data_get($payload, 'fawryRefNumber', data_get($payload, 'referenceNumber', $order->gateway_reference));

        $attributes = [
            'payment_method' => $order->payment_method ?: 'fawry',
            'provider' => 'fawry',
            'gateway_status' => $orderStatus !== '' ? $orderStatus : $order->gateway_status,
            'gateway_reference' => $gatewayReference !== '' ? $gatewayReference : $order->gateway_reference,
            'payment_last_synced_at' => now(),
            'payload' => $storedPayload,
        ];

        if ($orderStatus === 'PAID') {
            $attributes['status'] = 'paid_successfully';
            $attributes['paid_at'] = $this->parsePaymentTime(data_get($payload, 'paymentTime')) ?: now();
        }

        if (in_array($orderStatus, ['UNPAID', 'FAILED', 'EXPIRED', 'CANCELED'], true) && $order->status !== 'paid_successfully') {
            $attributes['status'] = 'pending_payment';
            $attributes['checkout_url'] = null;
        }

        $order = $this->sync($order->orderable, $attributes);

        if ($order->status === 'paid_successfully') {
            $this->syncOrderableStatus($order, 'paid_successfully');
        }

        return $order;
    }

    public function findByMerchantReference(string $merchantRefNum, ?string $orderableType = null): ?Order
    {
        $query = Order::query()
            ->with('orderable')
            ->where('merchant_ref_num', $merchantRefNum);

        if ($orderableType) {
            $query->where('orderable_type', $orderableType);
        }

        return $query->first();
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function parsePaymentTime(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestampMs((int) $value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildMerchantRefNum(Order $order): string
    {
        $order->loadMissing('orderable');
        $orderable = $order->orderable;
        $prefix = trim((string) config('services.fawry.merchant_ref_prefix', ''));

        $reference = match (true) {
            $orderable instanceof AdRequest => sprintf('AD%d', $orderable->id),
            $orderable instanceof CourseBooking => sprintf('CB%d', $orderable->id),
            default => sprintf('ORD%d', $order->id),
        };

        return $prefix !== ''
            ? preg_replace('/[^A-Za-z0-9]/', '', $prefix . $reference)
            : $reference;
    }

    private function syncOrderableStatus(Order $order, string $status): void
    {
        $order->loadMissing('orderable');
        $orderable = $order->orderable;

        if ($orderable instanceof AdRequest) {
            if ($orderable->status === $status && ! ($status === 'paid_successfully' && blank($orderable->starts_at))) {
                return;
            }

            $orderable->status = $status;

            if ($status === 'paid_successfully') {
                $startsAt = $order->paid_at ?: now();
                $orderable->starts_at = $orderable->starts_at ?: $startsAt;
                $orderable->ends_at = $orderable->ends_at ?: $startsAt->copy()->addMonthsNoOverflow((int) $orderable->duration_months);
            }

            $orderable->save();

            return;
        }

        if ($orderable instanceof CourseBooking) {
            if ($orderable->status === $status && ! ($status === 'paid_successfully' && blank($orderable->paid_at))) {
                return;
            }

            $orderable->status = $status;

            if ($status === 'paid_successfully') {
                $orderable->paid_at = $orderable->paid_at ?: ($order->paid_at ?: now());
            }

            $orderable->save();
        }
    }
}
