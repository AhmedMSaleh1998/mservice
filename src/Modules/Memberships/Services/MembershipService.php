<?php

namespace Modules\Memberships\Services;

use App\Services\Oracle\OracleDoctorDataLookupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrderService;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class MembershipService
{
    private const DEFAULT_SPECIALTY = 'طبيب';

    private const DEFAULT_DEGREE = 'بكالوريوس طب وجراحة';

    private const DEFAULT_REGISTRATION_NUMBER = 'TEMP-REGISTRATION-NUMBER';

    private const MEMBERSHIP_SERVICE_KEY = 'membership-id';

    private readonly SubscriptionChargeService $subscriptionChargeService;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OracleDoctorDataLookupService $oracleDoctorDataLookupService,
        ?SubscriptionChargeService $subscriptionChargeService = null,
    ) {
        $this->subscriptionChargeService = $subscriptionChargeService ?? app(SubscriptionChargeService::class);
    }

    public function calculateCosts(UserAddress $address, array $subscriptionCharge = []): array
    {
        $printingCost = $this->resolvePrintingCost();
        $deliveryCost = (float) ($address->province?->shipping_cost ?? 0);
        $subscriptionCost = (float) ($subscriptionCharge['amount'] ?? 0);

        return [
            'printing_cost' => $this->formatMoney($printingCost),
            'delivery_cost' => $this->formatMoney($deliveryCost),
            'subscription_cost' => $this->formatMoney($subscriptionCost),
            'subscription_years' => max((int) ($subscriptionCharge['years'] ?? 0), 0),
            'total_amount' => $this->formatMoney($printingCost + $deliveryCost + $subscriptionCost),
        ];
    }

    public function buildSummary(MembershipRequest $membershipRequest): array
    {
        $membershipRequest->loadMissing('userAddress.province');
        $order = $membershipRequest->relationLoaded('order')
            ? $membershipRequest->getRelation('order')
            : null;

        if ($order && ($summary = $this->orderService->pricingSummary($order))) {
            return $summary;
        }

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

        if ((float) $membershipRequest->subscription_cost > 0) {
            $items[] = [
                'code' => 'subscription_fees',
                'label' => __('Subscription fees'),
                'amount' => $this->formatMoney($membershipRequest->subscription_cost),
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
            $subscriptionCharge = $this->resolveSubscriptionCharge($user);

            $snapshot = $this->buildProfileSnapshot($user);

            if ($snapshot['full_name'] === '') {
                throw ValidationException::withMessages([
                    'profile' => __('The authenticated user name is required.'),
                ]);
            }

            $costs = $this->calculateCosts($address, $subscriptionCharge);
            $pricing = $this->buildPricingSummary($costs);
            $isFreeRequest = $this->orderService->isFreeAmount($costs['total_amount']);
            $requestStatus = $isFreeRequest ? 'paid_successfully' : 'pending_payment';
            $paymentMethod = $isFreeRequest ? 'free' : null;

            $membershipRequest = MembershipRequest::create([
                'user_id' => $user->id,
                'full_name' => $snapshot['full_name'],
                'specialty' => $snapshot['specialty'],
                'degree' => $snapshot['degree'],
                'registration_number' => $snapshot['registration_number'],
                'delivery_method' => 'delivery',
                'payment_method' => $paymentMethod,
                'user_address_id' => $address->id,
                'printing_cost' => $costs['printing_cost'],
                'delivery_cost' => $costs['delivery_cost'],
                'subscription_cost' => $costs['subscription_cost'],
                'total_amount' => $costs['total_amount'],
                'status' => $requestStatus,
                'delivery_status' => MembershipRequest::DELIVERY_STATUS_PENDING,
            ]);

            if (! $isFreeRequest) {
                $this->orderService->sync($membershipRequest, [
                    'user_id' => $user->id,
                    'amount' => $costs['total_amount'],
                    'status' => $requestStatus,
                    'payment_method' => null,
                    'payload' => $this->orderService->withPricingPayload(
                        $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                        $pricing,
                    ),
                ]);
            }

            return $membershipRequest->fresh(['userAddress.province', 'order']);
        });
    }

    public function buildProfileSnapshot(User $user): array
    {
        $oracleProfile = $this->loadOracleProfile($user);

        return [
            'full_name' => $this->firstNonEmpty(
                (string) ($user->name ?? ''),
                (string) data_get($oracleProfile, 'doctor_name', ''),
            ),
            'specialty' => $this->firstNonEmpty(
                (string) data_get($user, 'specialty', ''),
                (string) data_get($oracleProfile, 'specialization_arabic_name', ''),
                self::DEFAULT_SPECIALTY,
            ),
            'degree' => $this->firstNonEmpty(
                (string) data_get($user, 'degree', ''),
                (string) data_get($oracleProfile, 'consult_name', ''),
                self::DEFAULT_DEGREE,
            ),
            'registration_number' => $this->firstNonEmpty(
                (string) ($user->reg_number ?? ''),
                (string) data_get($oracleProfile, 'register_no', ''),
                self::DEFAULT_REGISTRATION_NUMBER,
            ),
            'oracle_profile' => $oracleProfile,
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

    private function loadOracleProfile(User $user): ?array
    {
        $registerNo = trim((string) ($user->reg_number ?? ''));
        if ($registerNo === '') {
            return null;
        }

        try {
            return $this->oracleDoctorDataLookupService->findByRegisterNo($registerNo);
        } catch (RuntimeException $exception) {
            Log::warning('Oracle doctor data lookup failed while building membership profile snapshot.', [
                'user_id' => $user->id,
                'register_no' => $registerNo,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
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
                'code' => 'membership_printing',
                'unit_price' => $costs['printing_cost'],
                'quantity' => 1,
                'amount' => $costs['printing_cost'],
            ],
        ];

        if ((float) $costs['delivery_cost'] > 0) {
            $items[] = [
                'code' => 'membership_shipping',
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
