<?php

namespace Modules\Certificates\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Services\OrderService;
use Modules\Users\Models\UserAddress;

class CertificateRequestService
{
    private const SUBSCRIPTION_COST = 0;

    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function calculateCosts(Certificate $certificate, ?UserAddress $address, string $deliveryMethod): array
    {
        $printingCost = (float) $certificate->price;
        $deliveryCost = $deliveryMethod === 'delivery'
            ? (float) ($address?->province?->shipping_cost ?? 0)
            : 0;
        $subscriptionCost = self::SUBSCRIPTION_COST;

        return [
            'printing_cost' => $this->formatMoney($printingCost),
            'delivery_cost' => $this->formatMoney($deliveryCost),
            'subscription_cost' => $this->formatMoney($subscriptionCost),
            'total_amount' => $this->formatMoney($printingCost + $deliveryCost + $subscriptionCost),
        ];
    }

    public function buildSummary(CertificateRequest $certificateRequest): array
    {
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
                'code' => 'certificate_subscription',
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
            $certificate = $this->resolveCertificate((int) $data['certificate_id']);
            $address = $this->resolveAddress($userId, $data['delivery_method'], $data['address_id'] ?? null);
            $costs = $this->calculateCosts($certificate, $address, $data['delivery_method']);

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
                'status' => CertificateRequest::STATUS_PENDING_PAYMENT,
                'delivery_status' => $data['delivery_method'] === 'delivery'
                    ? CertificateRequest::DELIVERY_STATUS_PENDING
                    : null,
            ]);

            $this->orderService->sync($certificateRequest, [
                'user_id' => $userId,
                'amount' => $costs['total_amount'],
                'status' => CertificateRequest::STATUS_PENDING_PAYMENT,
            ]);

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
}
