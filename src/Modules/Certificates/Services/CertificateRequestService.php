<?php

namespace Modules\Certificates\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Services\OrderService;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CertificateRequestService
{
    private readonly SubscriptionChargeService $subscriptionChargeService;

    public function __construct(
        private readonly OrderService $orderService,
        ?SubscriptionChargeService $subscriptionChargeService = null,
    ) {
        $this->subscriptionChargeService = $subscriptionChargeService ?? app(SubscriptionChargeService::class);
    }

    public function calculateCosts(
        Certificate $certificate,
        ?UserAddress $address,
        string $deliveryMethod,
        array $subscriptionCharge = [],
    ): array
    {
        $printingCost = (float) $certificate->price;
        $deliveryCost = $deliveryMethod === 'delivery'
            ? (float) ($address?->province?->shipping_cost ?? 0)
            : 0;
        $subscriptionCost = (float) ($subscriptionCharge['amount'] ?? 0);

        return [
            'printing_cost' => $this->formatMoney($printingCost),
            'delivery_cost' => $this->formatMoney($deliveryCost),
            'subscription_cost' => $this->formatMoney($subscriptionCost),
            'subscription_years' => max((int) ($subscriptionCharge['years'] ?? 0), 0),
            'total_amount' => $this->formatMoney($printingCost + $deliveryCost + $subscriptionCost),
        ];
    }

    public function buildSummary(CertificateRequest $certificateRequest): array
    {
        $order = $certificateRequest->relationLoaded('order')
            ? $certificateRequest->getRelation('order')
            : null;

        if ($order && ($summary = $this->orderService->pricingSummary($order))) {
            return $summary;
        }

        $items = [
            [
                'code' => 'certificate_printing',
                'label' => __('Certificate printing'),
                'amount' => $this->formatMoney($certificateRequest->printing_cost),
            ],
        ];

        if ((float) $certificateRequest->delivery_cost > 0) {
            $items[] = [
                'code' => 'certificate_shipping',
                'label' => __('Shipping fees'),
                'amount' => $this->formatMoney($certificateRequest->delivery_cost),
            ];
        }

        if ((float) $certificateRequest->subscription_cost > 0) {
            $items[] = [
                'code' => 'subscription_fees',
                'label' => __('Subscription fees'),
                'amount' => $this->formatMoney($certificateRequest->subscription_cost),
            ];
        }

        return [
            'title' => __('Payment Summary'),
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $items,
            'subtotal' => $this->formatMoney($certificateRequest->total_amount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($certificateRequest->total_amount),
        ];
    }

    public function makeRequest(array $data, int $userId): CertificateRequest
    {
        return DB::transaction(function () use ($data, $userId): CertificateRequest {
            $user = User::query()->findOrFail($userId);
            $certificate = $this->resolveCertificate((int) $data['certificate_id']);
            $address = $this->resolveAddress($userId, $data['delivery_method'], $data['address_id'] ?? null);
            $subscriptionCharge = $this->resolveSubscriptionCharge($user);
            $costs = $this->calculateCosts($certificate, $address, $data['delivery_method'], $subscriptionCharge);
            $pricing = $this->buildPricingSummary($costs);
            $isFreeRequest = $this->orderService->isFreeAmount($costs['total_amount']);
            $requestStatus = $isFreeRequest
                ? CertificateRequest::STATUS_PAID_SUCCESSFULLY
                : CertificateRequest::STATUS_PENDING_PAYMENT;

            $certificateRequest = CertificateRequest::create([
                'user_id' => $userId,
                'certificate_id' => $certificate->id,
                'delivery_method' => $data['delivery_method'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'user_address_id' => $address?->id,
                'printing_cost' => $costs['printing_cost'],
                'delivery_cost' => $costs['delivery_cost'],
                'subscription_cost' => $costs['subscription_cost'],
                'total_amount' => $costs['total_amount'],
                'status' => $requestStatus,
                'delivery_status' => $data['delivery_method'] === 'delivery'
                    ? CertificateRequest::DELIVERY_STATUS_PENDING
                    : null,
            ]);

            if (! $isFreeRequest) {
                $this->orderService->sync($certificateRequest, [
                    'user_id' => $userId,
                    'amount' => $costs['total_amount'],
                    'status' => $requestStatus,
                    'payload' => $this->orderService->withPricingPayload(
                        $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                        $pricing,
                    ),
                ]);
            }

            return $certificateRequest->fresh(['certificate.media', 'userAddress.province', 'order']);
        });
    }

    private function resolveCertificate(int $certificateId): Certificate
    {
        $certificate = Certificate::query()
            ->whereKey($certificateId)
            ->where('is_active', true)
            ->first();

        if (! $certificate) {
            throw ValidationException::withMessages([
                'certificate_id' => __('The selected certificate is invalid.'),
            ]);
        }

        return $certificate;
    }

    private function resolveAddress(int $userId, string $deliveryMethod, ?int $addressId): ?UserAddress
    {
        if ($deliveryMethod !== 'delivery') {
            return null;
        }

        if (! $addressId) {
            throw ValidationException::withMessages([
                'address_id' => __('Please select a delivery address.'),
            ]);
        }

        $address = UserAddress::query()
            ->with('province')
            ->where('user_id', $userId)
            ->find($addressId);

        if (! $address) {
            throw ValidationException::withMessages([
                'address_id' => __('The selected address is invalid.'),
            ]);
        }

        return $address;
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

    private function buildPricingSummary(array $costs): array
    {
        $items = [
            [
                'code' => 'certificate_printing',
                'unit_price' => $costs['printing_cost'],
                'quantity' => 1,
                'amount' => $costs['printing_cost'],
            ],
        ];

        if ((float) $costs['delivery_cost'] > 0) {
            $items[] = [
                'code' => 'certificate_shipping',
                'unit_price' => $costs['delivery_cost'],
                'quantity' => 1,
                'amount' => $costs['delivery_cost'],
            ];
        }

        if ((float) $costs['subscription_cost'] > 0) {
            $items[] = [
                'code' => 'subscription_fees',
                'unit_price' => $costs['subscription_cost'],
                'quantity' => 1,
                'amount' => $costs['subscription_cost'],
                'meta' => [
                    'subscription_years' => max((int) ($costs['subscription_years'] ?? 0), 0),
                ],
            ];
        }

        return [
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $items,
            'subtotal' => $costs['total_amount'],
            'discount' => 0,
            'fees' => 0,
            'total' => $costs['total_amount'],
        ];
    }
}
