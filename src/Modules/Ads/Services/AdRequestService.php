<?php

namespace Modules\Ads\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;
use Modules\Core\Services\OrderService;

class AdRequestService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
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
        return DB::transaction(function () use ($data, $userId) {
            $adSpace = AdSpace::query()
                ->lockForUpdate()
                ->findOrFail($data['ad_space_id']);

            if (! $adSpace->is_available) {
                throw ValidationException::withMessages([
                    'ad_space_id' => __('This ad space is no longer available.'),
                ]);
            }

            $adSpace->forceFill([
                'is_available' => false,
            ])->save();

            $durationMonths = (int) $data['duration_months'];
            $pricePerMonth = (float) $adSpace->price_per_month;
            $totalAmount = $pricePerMonth * $durationMonths;

            $adRequest = AdRequest::create([
                'user_id' => $userId,
                'ad_space_id' => $adSpace->id,
                'duration_months' => $durationMonths,
                'price_per_month' => $pricePerMonth,
                'total_amount' => $totalAmount,
                'ad_text' => $data['ad_text'] ?? null,
                'design_image' => ($data['design_image'] ?? null)?->getClientOriginalName(),
                'status' => 'pending_payment',
            ]);

            $file = $data['design_image'] ?? null;
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
            ]);

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

        $serviceTitle = (string) data_get($adRequest, 'adSpace.service.title', 'Ad space');
        $currency = (string) config('checkout.currency', 'EGP');
        $pricePerMonth = (float) $adRequest->price_per_month;
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
                    'amount' => $this->formatMoney($totalAmount),
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
}
