<?php

namespace Modules\Ads\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;
use Modules\Core\Services\OrderService;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Users\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AdRequestService
{
    private readonly SubscriptionChargeService $subscriptionChargeService;

    public function __construct(
        private readonly OrderService $orderService,
        ?SubscriptionChargeService $subscriptionChargeService = null,
    ) {
        $this->subscriptionChargeService = $subscriptionChargeService ?? app(SubscriptionChargeService::class);
    }

    public function listApproved()
    {
        return AdRequest::query()
            ->approved()
            ->with(['adSpace', 'media', 'order'])
            ->latest()
            ->get();
    }

    public function create(array $data, int $userId): AdRequest
    {
        $user = User::query()->findOrFail($userId);
        $subscriptionCharge = $this->resolveSubscriptionCharge($user);

        return DB::transaction(function () use ($data, $userId, $subscriptionCharge) {
            $adSpace = AdSpace::query()
                ->lockForUpdate()
                ->findOrFail($data['ad_space_id']);

            $editableRequest = AdRequest::query()
                ->where('user_id', $userId)
                ->where('ad_space_id', $adSpace->id)
                ->whereIn('status', AdRequest::EDITABLE_PRE_PAYMENT_STATUSES)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($editableRequest && $this->isReservationExpired($editableRequest)) {
                $this->expireReservation($editableRequest);
                $editableRequest = null;
                $adSpace = AdSpace::query()
                    ->lockForUpdate()
                    ->findOrFail($data['ad_space_id']);
            }

            $ownsCurrentReservation = $editableRequest
                && in_array($editableRequest->status, AdRequest::ACTIVE_RESERVATION_STATUSES, true);

            if (! $adSpace->is_available && ! $ownsCurrentReservation) {
                throw ValidationException::withMessages([
                    'ad_space_id' => __('This ad space is no longer available.'),
                ]);
            }

            if ($adSpace->is_available) {
                $adSpace->forceFill([
                    'is_available' => false,
                ])->save();
            }

            $durationMonths = (int) $data['duration_months'];
            $pricePerMonth = (float) $adSpace->price_per_month;
            $baseAmount = $pricePerMonth * $durationMonths;
            $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
            $totalAmount = $baseAmount + $subscriptionAmount;
            $file = $data['design_image'] ?? null;
            $pricing = $this->buildPricingSummary($adSpace, $durationMonths, $pricePerMonth, $baseAmount, $subscriptionCharge);

            if ($editableRequest) {
                $editableRequest->forceFill([
                    'duration_months' => $durationMonths,
                    'price_per_month' => $pricePerMonth,
                    'total_amount' => $totalAmount,
                    'ad_text' => $data['ad_text'] ?? $editableRequest->ad_text,
                    'status' => 'pending_payment',
                    'starts_at' => null,
                    'ends_at' => null,
                ]);

                if ($file) {
                    $editableRequest->design_image = $file->getClientOriginalName();
                }

                $editableRequest->save();

                if ($file) {
                    $editableRequest
                        ->addMedia($file)
                        ->toMediaCollection('design_image');
                }

                $adRequest = $editableRequest;

                $this->orderService->sync($adRequest, [
                    'user_id' => $userId,
                    'amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'payment_method' => null,
                    'provider' => null,
                    'merchant_ref_num' => null,
                    'gateway_reference' => null,
                    'gateway_status' => null,
                    'checkout_url' => null,
                    'payment_started_at' => null,
                    'payment_last_synced_at' => null,
                    'paid_at' => null,
                    'payload' => $this->orderService->withPricingPayload(
                        $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                        $pricing,
                    ),
                ]);
            } else {
                $adRequest = AdRequest::create([
                    'user_id' => $userId,
                    'ad_space_id' => $adSpace->id,
                    'duration_months' => $durationMonths,
                    'price_per_month' => $pricePerMonth,
                    'total_amount' => $totalAmount,
                    'ad_text' => $data['ad_text'] ?? null,
                    'design_image' => $file?->getClientOriginalName(),
                    'status' => 'pending_payment',
                ]);

                if ($file) {
                    $adRequest
                        ->addMedia($file)
                        ->toMediaCollection('design_image');
                }

                $this->orderService->sync($adRequest, [
                    'user_id' => $userId,
                    'amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'payment_method' => $data['payment_method'] ?? null,
                    'payload' => $this->orderService->withPricingPayload(
                        $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                        $pricing,
                    ),
                ]);
            }

            return $adRequest->fresh(['adSpace.service', 'media', 'order']);
        });
    }

    public function reservationTimeoutMinutes(): int
    {
        $minutes = (int) config('checkout.reservation_timeout_minutes', 5);

        return $minutes > 0 ? $minutes : 5;
    }

    public function reservationExpiresAt(AdRequest $adRequest): Carbon
    {
        $createdAt = $adRequest->created_at instanceof Carbon
            ? $adRequest->created_at->copy()
            : Carbon::now();

        return $createdAt->addMinutes($this->reservationTimeoutMinutes());
    }

    public function isReservationExpired(AdRequest $adRequest): bool
    {
        return $this->reservationExpiresAt($adRequest)->lte(Carbon::now());
    }

    public function expireReservation(AdRequest $adRequest): bool
    {
        return DB::transaction(function () use ($adRequest): bool {
            $lockedAdRequest = AdRequest::query()
                ->lockForUpdate()
                ->find($adRequest->id);

            if (! $lockedAdRequest || $lockedAdRequest->status !== 'pending_payment') {
                return false;
            }

            if (! $this->isReservationExpired($lockedAdRequest)) {
                return false;
            }

            $order = $lockedAdRequest->order()->lockForUpdate()->first();
            if ($order && $order->status === 'paid_successfully') {
                return false;
            }

            $adSpace = AdSpace::query()
                ->lockForUpdate()
                ->find($lockedAdRequest->ad_space_id);

            if ($adSpace && ! $adSpace->is_available) {
                $adSpace->forceFill([
                    'is_available' => true,
                ])->save();
            }

            $lockedAdRequest->status = 'payment_expired';
            $lockedAdRequest->save();

            if ($order) {
                $order->status = 'payment_expired';
                $order->checkout_url = null;
                $order->payment_last_synced_at = now();

                if ($order->status !== 'paid_successfully') {
                    $order->gateway_status = 'EXPIRED';
                }

                $order->save();
            }

            return true;
        });
    }

    public function cancel(AdRequest $adRequest): AdRequest
    {
        return DB::transaction(function () use ($adRequest): AdRequest {
            $lockedAdRequest = AdRequest::query()
                ->lockForUpdate()
                ->findOrFail($adRequest->id);

            $order = $lockedAdRequest->order()->lockForUpdate()->first();

            if ($lockedAdRequest->status === 'cancelled' && $order?->status === 'cancelled') {
                return $lockedAdRequest->fresh(['adSpace.service', 'media', 'order']);
            }

            if ($lockedAdRequest->status !== 'pending_payment' || $order?->status !== 'pending_payment') {
                throw ValidationException::withMessages([
                    'ad_request' => __('This ad request can only be cancelled before payment starts.'),
                ]);
            }

            $adSpace = AdSpace::query()
                ->lockForUpdate()
                ->find($lockedAdRequest->ad_space_id);

            if ($adSpace && ! $adSpace->is_available) {
                $adSpace->forceFill([
                    'is_available' => true,
                ])->save();
            }

            $lockedAdRequest->forceFill([
                'status' => 'cancelled',
                'starts_at' => null,
                'ends_at' => null,
            ])->save();

            if ($order) {
                $order->forceFill([
                    'status' => 'cancelled',
                    'checkout_url' => null,
                    'payment_last_synced_at' => now(),
                ])->save();
            }

            return $lockedAdRequest->fresh(['adSpace.service', 'media', 'order']);
        });
    }

    public function completeFinishedReservation(AdRequest $adRequest): bool
    {
        return DB::transaction(function () use ($adRequest): bool {
            $lockedAdRequest = AdRequest::query()
                ->lockForUpdate()
                ->find($adRequest->id);

            if (! $lockedAdRequest || ! in_array($lockedAdRequest->status, ['paid_successfully', 'approved'], true)) {
                return false;
            }

            if (! $lockedAdRequest->ends_at instanceof Carbon || $lockedAdRequest->ends_at->isFuture()) {
                return false;
            }

            $adSpace = AdSpace::query()
                ->lockForUpdate()
                ->find($lockedAdRequest->ad_space_id);

            if ($adSpace && ! $adSpace->is_available) {
                $adSpace->forceFill([
                    'is_available' => true,
                ])->save();
            }

            $lockedAdRequest->status = 'completed';
            $lockedAdRequest->save();

            return true;
        });
    }

    public function buildSummary(AdRequest $adRequest): array
    {
        $adRequest->loadMissing('adSpace.service');
        $order = $adRequest->relationLoaded('order')
            ? $adRequest->getRelation('order')
            : null;

        if ($order && ($summary = $this->orderService->pricingSummary($order))) {
            return $summary;
        }

        $serviceTitle = (string) data_get($adRequest, 'adSpace.service.title', 'Ad space');
        $currency = (string) config('checkout.currency', 'EGP');
        $pricePerMonth = (float) $adRequest->price_per_month;
        $baseAmount = $pricePerMonth * (int) $adRequest->duration_months;
        $totalAmount = (float) $adRequest->total_amount;

        return [
            'title' => __('Payment Summary'),
            'currency' => $currency,
            'items' => [
                [
                    'code' => 'ad_space_booking',
                    'label' => __('Ad space booking'),
                    'description' => $serviceTitle,
                    'unit_price' => $this->formatMoney($pricePerMonth),
                    'quantity' => (int) $adRequest->duration_months,
                    'amount' => $this->formatMoney($baseAmount),
                ],
            ],
            'subtotal' => $this->formatMoney($totalAmount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($totalAmount),
        ];
    }

    private function formatMoney(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function resolveSubscriptionCharge(User $user): array
    {
        try {
            return $this->subscriptionChargeService->resolveForUser($user);
        } catch (RuntimeException $exception) {
            throw new ServiceUnavailableHttpException(
                null,
                __('Unable to verify subscription fees with Oracle at the moment. Please try again later.'),
                $exception,
            );
        }
    }

    private function buildPricingSummary(
        AdSpace $adSpace,
        int $durationMonths,
        float $pricePerMonth,
        float $baseAmount,
        array $subscriptionCharge,
    ): array {
        $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
        $totalAmount = $baseAmount + $subscriptionAmount;
        $items = [
            [
                'code' => 'ad_space_booking',
                'description' => (string) data_get($adSpace, 'service.title', 'Ad space'),
                'unit_price' => $this->formatMoney($pricePerMonth),
                'quantity' => $durationMonths,
                'amount' => $this->formatMoney($baseAmount),
            ],
        ];

        if ($subscriptionAmount > 0) {
            $items[] = [
                'code' => 'subscription_fees',
                'unit_price' => $this->formatMoney($subscriptionAmount),
                'quantity' => 1,
                'amount' => $this->formatMoney($subscriptionAmount),
                'meta' => [
                    'subscription_years' => max((int) ($subscriptionCharge['years'] ?? 0), 0),
                ],
            ];
        }

        return [
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $items,
            'subtotal' => $this->formatMoney($totalAmount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($totalAmount),
        ];
    }
}
