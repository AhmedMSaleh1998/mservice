<?php

namespace Modules\Memberships\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrderService;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;

class MembershipService
{
    private const DEFAULT_SPECIALTY = 'طبيب';

    private const DEFAULT_DEGREE = 'بكالوريوس طب وجراحة';

    private const DEFAULT_REGISTRATION_NUMBER = 'TEMP-REGISTRATION-NUMBER';

    private const MEMBERSHIP_SERVICE_KEY = 'membership-id';

    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function calculateCosts(UserAddress $address): array
    {
        $printingCost = $this->resolvePrintingCost();
        $deliveryCost = (float) ($address->province?->shipping_cost ?? 0);
        $subscriptionCost = 0;

        return [
            'printing_cost' => $this->formatMoney($printingCost),
            'delivery_cost' => $this->formatMoney($deliveryCost),
            'subscription_cost' => $this->formatMoney($subscriptionCost),
            'total_amount' => $this->formatMoney($printingCost + $deliveryCost + $subscriptionCost),
        ];
    }

    public function buildSummary(MembershipRequest $membershipRequest): array
    {
        $membershipRequest->loadMissing('userAddress.province');

        $items = [
            [
                'code' => 'membership_printing',
                'label' => __('Membership printing'),
                'amount' => $this->formatMoney($membershipRequest->printing_cost),
            ],
        ];

        if ((float) $membershipRequest->delivery_cost > 0) {
            $items[] = [
                'code' => 'membership_shipping',
                'label' => __('Shipping fees'),
                'amount' => $this->formatMoney($membershipRequest->delivery_cost),
            ];
        }

        return [
            'title' => __('Payment Summary'),
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $items,
            'subtotal' => $this->formatMoney($membershipRequest->total_amount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($membershipRequest->total_amount),
        ];
    }

    public function createRequest(array $data, User $user): MembershipRequest
    {
        return DB::transaction(function () use ($data, $user): MembershipRequest {
            $address = $this->resolveAddress($user, $data['address_id'] ?? null);

            $snapshot = $this->buildProfileSnapshot($user);

            if ($snapshot['full_name'] === '') {
                throw ValidationException::withMessages([
                    'profile' => __('The authenticated user name is required.'),
                ]);
            }

            $costs = $this->calculateCosts($address);

            $membershipRequest = MembershipRequest::create([
                'user_id' => $user->id,
                'full_name' => $snapshot['full_name'],
                'specialty' => $snapshot['specialty'],
                'degree' => $snapshot['degree'],
                'registration_number' => $snapshot['registration_number'],
                'delivery_method' => 'delivery',
                'payment_method' => null,
                'user_address_id' => $address->id,
                'printing_cost' => $costs['printing_cost'],
                'delivery_cost' => $costs['delivery_cost'],
                'subscription_cost' => $costs['subscription_cost'],
                'total_amount' => $costs['total_amount'],
                'status' => 'pending_payment',
            ]);

            $this->orderService->sync($membershipRequest, [
                'user_id' => $user->id,
                'amount' => $costs['total_amount'],
                'status' => 'pending_payment',
                'payment_method' => null,
            ]);

            return $membershipRequest->fresh(['userAddress.province', 'order']);
        });
    }

    public function buildProfileSnapshot(User $user): array
    {
        return [
            'full_name' => trim((string) ($user->name ?? '')),
            'specialty' => $this->fallback((string) data_get($user, 'specialty', ''), self::DEFAULT_SPECIALTY),
            'degree' => $this->fallback((string) data_get($user, 'degree', ''), self::DEFAULT_DEGREE),
            'registration_number' => $this->fallback(
                (string) ($user->reg_number ?? ''),
                self::DEFAULT_REGISTRATION_NUMBER
            ),
        ];
    }

    private function resolveAddress(User $user, ?int $addressId): UserAddress
    {
        if (! $addressId) {
            throw ValidationException::withMessages([
                'address_id' => __('Please select a delivery address.'),
            ]);
        }

        $address = $user->addresses()
            ->with('province')
            ->find($addressId);

        if (! $address) {
            throw ValidationException::withMessages([
                'address_id' => __('The selected address is invalid.'),
            ]);
        }

        return $address;
    }

    private function fallback(string $value, string $fallback): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    private function resolvePrintingCost(): float
    {
        return (float) (Service::query()
            ->where('key', self::MEMBERSHIP_SERVICE_KEY)
            ->value('price') ?? 0);
    }

    private function formatMoney(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
