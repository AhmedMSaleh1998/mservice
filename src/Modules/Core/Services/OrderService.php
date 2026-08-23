<?php

namespace Modules\Core\Services;

use App\Services\Oracle\OraclePaymentSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Modules\Ads\Models\AdRequest;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnitBooking;
use Modules\Travels\Models\TravelBooking;

class OrderService
{
    private const SUBSCRIPTION_ITEM_CODES = [
        'subscription_fees',
        'membership_subscription',
        'certificate_subscription',
        'course_subscription',
        'ad_subscription',
    ];

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

        $isPaidOrder = $order->status === 'paid_successfully';
        $order->save();

        $freshOrder = $order->fresh(['orderable', 'user']);
        $freshOrder = $this->synchronizePaidOrder($freshOrder, $isPaidOrder);
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

    public function isFreeAmount(mixed $amount): bool
    {
        return (float) $amount <= 0;
    }

    public function deleteFor(Model $orderable): void
    {
        $orderable->order()->delete();
        $orderable->unsetRelation('order');
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
        return $this->sync($order->orderable, [
            'status' => 'paid_successfully',
            'payment_method' => $paymentMethod,
            'provider' => $paymentMethod === 'fawry' ? 'fawry' : 'manual',
            'gateway_status' => 'PAID',
            'paid_at' => $order->paid_at ?: now(),
            'payment_last_synced_at' => now(),
        ]);
    }

    public function confirmPayment(Order $order): Order
    {
        return $this->markPaid($order, (string) $order->payment_method);
    }

    public function applyFawryPaymentUpdate(Order $order, array $payload, string $source): Order
    {
        // Serialize concurrent updates for the same order (return redirect,
        // notification webhook and the reconciliation command can land within
        // the same second) so the Oracle export guard in synchronizePaidOrder
        // always sees the latest persisted state.
        return DB::transaction(function () use ($order, $payload, $source): Order {
            $locked = Order::query()
                ->with('orderable')
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            return $this->applyFawryPaymentUpdateLocked($locked ?? $order, $payload, $source);
        });
    }

    private function applyFawryPaymentUpdateLocked(Order $order, array $payload, string $source): Order
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

        return $this->sync($order->orderable, $attributes);
    }

    /**
     * Marks a Fawry checkout as expired once its gateway payment window has
     * passed, so pending orders reach a terminal state instead of being
     * re-checked forever. A one-minute grace period avoids racing a boundary
     * payment; a genuinely late PAID update still wins because
     * applyFawryPaymentUpdate always honours PAID.
     */
    public function expireStaleFawryCheckout(Order $order): Order
    {
        if ($order->payment_method !== 'fawry' || $order->status !== 'checkout_pending') {
            return $order;
        }

        $expiresAt = $this->fawryCheckoutExpiresAt($order);
        if (! $expiresAt || $expiresAt->copy()->addMinute()->isFuture()) {
            return $order;
        }

        return $this->applyFawryPaymentUpdate($order, [
            'orderStatus' => 'EXPIRED',
            'expiredLocally' => true,
            'checkoutExpiredAt' => $expiresAt->format('Y-m-d H:i:s'),
        ], 'local_expiry');
    }

    public function fawryCheckoutExpiresAt(Order $order): ?Carbon
    {
        $expiryMs = data_get($order->payload, 'charge_request.paymentExpiry');
        if (is_numeric($expiryMs) && (int) $expiryMs > 0) {
            return Carbon::createFromTimestampMs((int) $expiryMs);
        }

        if (! $order->payment_started_at) {
            return null;
        }

        $expiryMinutes = config('services.fawry.payment_expiry_minutes');
        if (is_numeric($expiryMinutes) && (int) $expiryMinutes > 0) {
            return $order->payment_started_at->copy()->addMinutes((int) $expiryMinutes);
        }

        return $order->payment_started_at->copy()->addHours((int) config('services.fawry.payment_expiry_hours', 24));
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

    public function withSubscriptionChargePayload(array|null $payload, array $subscriptionCharge): array
    {
        $normalizedPayload = is_array($payload) ? $payload : [];
        $normalizedPayload['subscription_charge'] = $this->normalizeSubscriptionChargeSnapshot($subscriptionCharge);

        return $normalizedPayload;
    }

    public function subscriptionChargeSnapshot(Order $order): ?array
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $subscriptionCharge = $payload['subscription_charge'] ?? null;

        return is_array($subscriptionCharge) ? $subscriptionCharge : null;
    }

    public function withPricingPayload(array|null $payload, array $pricing): array
    {
        $normalizedPayload = is_array($payload) ? $payload : [];
        $normalizedPayload['pricing'] = $this->buildPricingSnapshot($pricing);

        return $normalizedPayload;
    }

    public function pricingSnapshot(Order $order): ?array
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $pricing = $payload['pricing'] ?? null;

        return is_array($pricing) ? $pricing : null;
    }

    public function pricingSummary(Order $order): ?array
    {
        $pricing = $this->pricingSnapshot($order);
        if (! $pricing) {
            return null;
        }

        return [
            'title' => __('Payment Summary'),
            'currency' => (string) ($pricing['currency'] ?? config('checkout.currency', 'EGP')),
            'items' => collect($pricing['items'] ?? [])
                ->filter(static fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => [
                    'code' => filled($item['code'] ?? null) ? (string) $item['code'] : null,
                    'label' => $this->resolvePricingItemLabel($item),
                    'description' => $this->resolvePricingItemDescription($item),
                    'unit_price' => array_key_exists('unit_price', $item)
                        ? $this->formatAmount($item['unit_price'])
                        : null,
                    'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                    'amount' => $this->formatAmount($item['amount'] ?? 0),
                ])
                ->values()
                ->all(),
            'subtotal' => $this->formatAmount($pricing['subtotal'] ?? 0),
            'discount' => $this->formatAmount($pricing['discount'] ?? 0),
            'fees' => $this->formatAmount($pricing['fees'] ?? 0),
            'total' => $this->formatAmount($pricing['total'] ?? $order->amount),
        ];
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function buildPricingSnapshot(array $pricing): array
    {
        return [
            'currency' => (string) ($pricing['currency'] ?? config('checkout.currency', 'EGP')),
            'items' => collect($pricing['items'] ?? [])
                ->filter(static fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => array_filter([
                    'code' => filled($item['code'] ?? null) ? (string) $item['code'] : null,
                    'label' => filled($item['label'] ?? null) ? (string) $item['label'] : null,
                    'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
                    'unit_price' => array_key_exists('unit_price', $item)
                        ? $this->formatAmount($item['unit_price'])
                        : null,
                    'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                    'amount' => $this->formatAmount($item['amount'] ?? 0),
                    'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : null,
                ], static fn (mixed $value): bool => ! is_null($value)))
                ->values()
                ->all(),
            'subtotal' => $this->formatAmount($pricing['subtotal'] ?? $pricing['total'] ?? 0),
            'discount' => $this->formatAmount($pricing['discount'] ?? 0),
            'fees' => $this->formatAmount($pricing['fees'] ?? 0),
            'total' => $this->formatAmount($pricing['total'] ?? 0),
        ];
    }

    private function normalizeSubscriptionChargeSnapshot(array $subscriptionCharge): array
    {
        return [
            'register_no' => trim((string) ($subscriptionCharge['register_no'] ?? '')),
            'amount' => $this->formatAmount($subscriptionCharge['amount'] ?? 0),
            'years' => max((int) ($subscriptionCharge['years'] ?? 0), 0),
            'status' => is_numeric($subscriptionCharge['status'] ?? null)
                ? (int) $subscriptionCharge['status']
                : ($subscriptionCharge['status'] ?? null),
        ];
    }

    private function resolvePricingItemLabel(array $item): ?string
    {
        return match ((string) ($item['code'] ?? '')) {
            'ad_space_booking' => __('Ad space booking'),
            'course_booking' => __('Course booking'),
            'membership_printing' => __('Membership printing'),
            'membership_shipping', 'certificate_shipping' => __('Shipping fees'),
            'certificate_printing' => __('Certificate printing'),
            'rest_unit_stay' => __('Stay fees'),
            'travel_booking' => __('Travel booking'),
            default => in_array((string) ($item['code'] ?? ''), self::SUBSCRIPTION_ITEM_CODES, true)
                ? __('Subscription fees')
                : (filled($item['label'] ?? null) ? (string) $item['label'] : null),
        };
    }

    private function resolvePricingItemDescription(array $item): ?string
    {
        $code = (string) ($item['code'] ?? '');
        $years = max((int) data_get($item, 'meta.subscription_years', data_get($item, 'meta.years', 0)), 0);

        if (in_array($code, self::SUBSCRIPTION_ITEM_CODES, true) && $years > 0) {
            return __('Subscription covers :years years.', ['years' => $years]);
        }

        $description = trim((string) ($item['description'] ?? ''));

        return $description !== '' ? $description : null;
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
            $orderable instanceof CertificateRequest => sprintf('CERT%d', $orderable->id),
            $orderable instanceof CourseBooking => sprintf('CB%d', $orderable->id),
            $orderable instanceof MembershipRequest => sprintf('MID%d', $orderable->id),
            $orderable instanceof RestUnitBooking => sprintf('RUB%d', $orderable->id),
            $orderable instanceof TravelBooking => sprintf('TRV%d', $orderable->id),
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

            return;
        }

        if ($orderable instanceof MembershipRequest) {
            if ($orderable->status === $status) {
                return;
            }

            $orderable->status = $status;

            if (filled($order->payment_method)) {
                $orderable->payment_method = $order->payment_method;
            }

            $orderable->save();

            return;
        }

        if ($orderable instanceof CertificateRequest) {
            if ($orderable->status === $status) {
                return;
            }

            $orderable->status = $status;
            $orderable->save();

            return;
        }

        if ($orderable instanceof RestUnitBooking) {
            if ($orderable->status === $status && ! ($status === 'paid_successfully' && blank($orderable->paid_at))) {
                return;
            }

            $orderable->status = $status;

            if ($status === 'paid_successfully') {
                $orderable->paid_at = $orderable->paid_at ?: ($order->paid_at ?: now());
            }

            $orderable->save();

            return;
        }

        if ($orderable instanceof TravelBooking) {
            if ($orderable->status === $status && ! ($status === 'paid_successfully' && blank($orderable->paid_at))) {
                return;
            }

            $orderable->status = $status;

            if ($status === 'paid_successfully') {
                $orderable->paid_at = $order->paid_at ?: ($orderable->paid_at ?: now());
            }

            $orderable->save();
        }
    }

    private function synchronizePaidOrder(Order $order, bool $wasPaidBeforeSave): Order
    {
        if ($order->status !== 'paid_successfully') {
            return $order;
        }

        $this->syncOrderableStatus($order, 'paid_successfully');
        $order = $order->fresh(['orderable', 'user']);

        if ($wasPaidBeforeSave && $this->hasSuccessfulOraclePaymentSync($order)) {
            Log::info('Oracle payment sync skipped because order was already exported successfully.', [
                'order_id' => $order->id,
                'order_status' => $order->status,
                'gateway_status' => $order->gateway_status,
                'merchant_ref_num' => $order->merchant_ref_num,
                'gateway_reference' => $order->gateway_reference,
                'orderable_type' => $order->orderable_type,
                'orderable_id' => $order->orderable_id,
                'oracle_payment_sync' => data_get($order->payload, 'oracle_payment_sync'),
            ]);

            return $order;
        }

        try {
            $syncResult = app(OraclePaymentSyncService::class)->syncPaidOrder($order);
        } catch (Throwable $exception) {
            report($exception);

            $syncResult = [
                'status' => 'failed',
                'reason' => 'unexpected_error',
                'attempted_at' => now()->format('Y-m-d H:i:s'),
                'synced_at' => null,
                'payment_type' => null,
                'status_code' => null,
                'message' => $exception->getMessage(),
                'request' => null,
            ];
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['oracle_payment_sync'] = $syncResult;

        $order->forceFill([
            'payload' => $payload,
        ])->save();

        Log::log(($syncResult['status'] ?? null) === 'success' ? 'info' : 'warning', 'Oracle payment sync result stored on order.', [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'gateway_status' => $order->gateway_status,
            'merchant_ref_num' => $order->merchant_ref_num,
            'gateway_reference' => $order->gateway_reference,
            'orderable_type' => $order->orderable_type,
            'orderable_id' => $order->orderable_id,
            'sync_status' => $syncResult['status'] ?? null,
            'sync_reason' => $syncResult['reason'] ?? null,
            'sync_status_code' => $syncResult['status_code'] ?? null,
            'sync_message' => $syncResult['message'] ?? null,
            'synced_at' => $syncResult['synced_at'] ?? null,
        ]);

        return $order->fresh(['orderable', 'user']);
    }

    public function hasSuccessfulOraclePaymentSync(Order $order): bool
    {
        return data_get($order->payload, 'oracle_payment_sync.status') === 'success';
    }

    /**
     * Manually (re)run the Oracle payment sync for a single paid order and persist the result.
     * Used by the admin sync actions. Returns the sync result array.
     */
    public function runOraclePaymentSync(Order $order): array
    {
        if ($order->status !== 'paid_successfully') {
            return [
                'status' => 'skipped',
                'reason' => 'not_paid',
                'attempted_at' => now()->format('Y-m-d H:i:s'),
                'synced_at' => null,
                'payment_type' => null,
                'status_code' => null,
                'message' => __('Only paid orders can be synced with Oracle.'),
                'request' => null,
            ];
        }

        try {
            $syncResult = app(OraclePaymentSyncService::class)->syncPaidOrder($order);
        } catch (Throwable $exception) {
            report($exception);

            $syncResult = [
                'status' => 'failed',
                'reason' => 'unexpected_error',
                'attempted_at' => now()->format('Y-m-d H:i:s'),
                'synced_at' => null,
                'payment_type' => null,
                'status_code' => null,
                'message' => $exception->getMessage(),
                'request' => null,
            ];
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $payload['oracle_payment_sync'] = $syncResult;

        $order->forceFill([
            'payload' => $payload,
        ])->save();

        return $syncResult;
    }
}
