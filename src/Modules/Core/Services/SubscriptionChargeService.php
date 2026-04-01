<?php

namespace Modules\Core\Services;

use App\Services\Oracle\OracleSubscriptionChargeLookupService;
use Modules\Users\Models\User;

class SubscriptionChargeService
{
    public function __construct(
        private readonly OracleSubscriptionChargeLookupService $oracleSubscriptionChargeLookupService,
    ) {
    }

    public function resolveForUser(User $user): array
    {
        return $this->resolveForRegisterNo($user->reg_number);
    }

    public function resolveForRegisterNo(?string $registerNo): array
    {
        $normalizedRegisterNo = trim((string) $registerNo);
        $emptyCharge = [
            'register_no' => $normalizedRegisterNo,
            'amount' => 0.0,
            'years' => 0,
            'status' => null,
        ];

        if ($normalizedRegisterNo === '') {
            return $emptyCharge;
        }

        $subscriptionData = $this->oracleSubscriptionChargeLookupService->findByRegisterNo($normalizedRegisterNo);
        if (! is_array($subscriptionData)) {
            return $emptyCharge;
        }

        $status = is_numeric($subscriptionData['status'] ?? null)
            ? (int) $subscriptionData['status']
            : null;
        $amount = max((float) ($subscriptionData['amount'] ?? 0), 0);
        $years = max((int) ($subscriptionData['years'] ?? 0), 0);

        if ($status !== 200 || $amount <= 0) {
            return [
                ...$emptyCharge,
                'years' => $years,
                'status' => $status,
            ];
        }

        return [
            'register_no' => $normalizedRegisterNo,
            'amount' => $amount,
            'years' => $years,
            'status' => $status,
        ];
    }

    public function isDue(array $subscriptionCharge): bool
    {
        return (int) ($subscriptionCharge['status'] ?? 0) === 200
            && (float) ($subscriptionCharge['amount'] ?? 0) > 0;
    }
}
