<?php

namespace App\Services\Oracle;

use Illuminate\Support\Facades\Log;
use Modules\Ads\Models\AdRequest;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnitBooking;
use Modules\Travels\Models\TravelBooking;
use Modules\Users\Models\User;
use PDO;
use RuntimeException;

class OraclePaymentSyncService
{
    private const PAYMENT_SQL = <<<'SQL'
        BEGIN
            EMS_UN_BASIC_DATA.ITP_MOBILE_API.PAYMENT_SERVICE(
                P_REGISTRATIONNO => :p_registration_no,
                P_AMOUNT => :p_amount,
                P_PAYMENTTYPE => :p_payment_type,
                P_PAND_ID => :p_pand_id,
                P_BANKTRANSACTIONID => :p_bank_transaction_id,
                P_COURSE_ID => :p_course_id,
                P_PHONENUMBER => :p_phone_number,
                P_STATUSCODE => :p_status_code,
                P_MESSAGE => :p_message
            );
        END;
    SQL;

    public function __construct(
        private readonly OracleConnectionService $oracleConnectionService,
    ) {
    }

    public function syncPaidOrder(Order $order): array
    {
        $order->loadMissing('orderable', 'user');

        $paymentParts = $this->buildPaymentDataParts($order);
        $paymentData = $this->summarizePaymentParts($paymentParts);
        $attemptedAt = now()->format('Y-m-d H:i:s');

        $this->logPaymentSyncEvent('Oracle payment sync requested.', $order, $paymentData, [
            'attempted_at' => $attemptedAt,
            'enabled' => (bool) config('services.oracle.payment_sync_enabled', true),
            'configured' => $this->isConfigured(),
            'parts_count' => count($paymentParts),
        ]);

        if (! config('services.oracle.payment_sync_enabled', true)) {
            return $this->logAndReturnResult($order, $paymentData, $this->buildSkippedResult($paymentData, $attemptedAt, 'disabled', 'Oracle payment sync is disabled.'));
        }

        if (! $this->isConfigured()) {
            return $this->logAndReturnResult($order, $paymentData, $this->buildSkippedResult($paymentData, $attemptedAt, 'not_configured', 'Oracle payment sync is not configured.'));
        }

        if ($paymentParts === [] || collect($paymentParts)->contains(fn (array $part): bool => $part['payment_type'] === null)) {
            return $this->logAndReturnResult($order, $paymentData, $this->buildSkippedResult($paymentData, $attemptedAt, 'unsupported_payment_type', 'This paid order type is not supported by Oracle PAYMENT_SERVICE.'));
        }

        if ((float) $paymentData['amount'] <= 0) {
            return $this->logAndReturnResult($order, $paymentData, $this->buildSkippedResult($paymentData, $attemptedAt, 'zero_amount', 'Zero-amount payments are not synced to Oracle.'));
        }

        foreach ($paymentParts as $part) {
            $missingField = $this->resolveMissingField($part);
            if ($missingField !== null) {
                return $this->logAndReturnResult($order, $paymentData, [
                    'status' => 'failed',
                    'reason' => 'missing_required_data',
                    'attempted_at' => $attemptedAt,
                    'synced_at' => null,
                    'payment_type' => $part['payment_type'],
                    'status_code' => null,
                    'message' => sprintf('Oracle payment sync is missing required field: %s.', $missingField),
                    'request' => $paymentData,
                    'parts' => [
                        [
                            'status' => 'failed',
                            'reason' => 'missing_required_data',
                            'payment_type' => $part['payment_type'],
                            'status_code' => null,
                            'message' => sprintf('Oracle payment sync is missing required field: %s.', $missingField),
                            'request' => $part,
                        ],
                    ],
                ]);
            }
        }

        $connection = $this->oracleConnectionService->make();
        $driver = $connection instanceof PDO ? 'pdo_oci' : 'oci8';

        $partResults = [];

        foreach ($paymentParts as $part) {
            Log::info('Oracle payment sync started.', [
                'driver' => $driver,
                'order_id' => $order->id,
                'payment_type' => $part['payment_type'],
                'amount' => $part['amount'],
                'part' => $part['part'] ?? null,
            ]);

            try {
                [$statusCode, $message] = $connection instanceof PDO
                    ? $this->syncWithPdo($connection, $part)
                    : $this->syncWithOci8($connection, $part);
            } catch (RuntimeException $exception) {
                Log::error('Oracle payment sync failed.', [
                    'driver' => $driver,
                    'order_id' => $order->id,
                    'payment_type' => $part['payment_type'],
                    'amount' => $part['amount'],
                    'part' => $part['part'] ?? null,
                    'error' => $exception->getMessage(),
                ]);

                $partResults[] = [
                    'status' => 'failed',
                    'reason' => 'oracle_error',
                    'payment_type' => $part['payment_type'],
                    'status_code' => null,
                    'message' => $exception->getMessage(),
                    'request' => $part,
                ];

                return $this->logAndReturnResult($order, $paymentData, [
                    'status' => 'failed',
                    'reason' => 'oracle_error',
                    'attempted_at' => $attemptedAt,
                    'synced_at' => null,
                    'payment_type' => $part['payment_type'],
                    'status_code' => null,
                    'message' => $exception->getMessage(),
                    'request' => $paymentData,
                    'parts' => $partResults,
                ]);
            }

            $normalizedStatusCode = $this->normalizeStatusCode($statusCode);
            $normalizedMessage = trim($message);
            $success = $this->isSuccessfulPaymentResponse($part, $normalizedStatusCode);

            Log::info('Oracle PAYMENT_SERVICE response received.', [
                ...$this->paymentSyncLogContext($order, $part),
                'part' => $part['part'] ?? null,
                'raw_status_code' => $statusCode,
                'raw_message' => $message,
                'normalized_status_code' => $normalizedStatusCode,
                'normalized_message' => $normalizedMessage,
            ]);

            Log::log($success ? 'info' : 'warning', 'Oracle payment sync completed.', [
                'driver' => $driver,
                'order_id' => $order->id,
                'payment_type' => $part['payment_type'],
                'amount' => $part['amount'],
                'part' => $part['part'] ?? null,
                'status_code' => $normalizedStatusCode,
                'message' => $normalizedMessage,
            ]);

            $partResult = [
                'status' => $success ? 'success' : 'failed',
                'reason' => $success ? null : 'oracle_rejected',
                'payment_type' => $part['payment_type'],
                'status_code' => $normalizedStatusCode,
                'message' => $normalizedMessage !== '' ? $normalizedMessage : null,
                'request' => $part,
            ];

            $partResults[] = $partResult;

            if (! $success) {
                return $this->logAndReturnResult($order, $paymentData, [
                    'status' => 'failed',
                    'reason' => 'oracle_rejected',
                    'attempted_at' => $attemptedAt,
                    'synced_at' => null,
                    'payment_type' => $part['payment_type'],
                    'status_code' => $normalizedStatusCode,
                    'message' => $normalizedMessage !== '' ? $normalizedMessage : null,
                    'request' => $paymentData,
                    'parts' => $partResults,
                ]);
            }
        }

        return $this->logAndReturnResult($order, $paymentData, [
            'status' => 'success',
            'reason' => null,
            'attempted_at' => $attemptedAt,
            'synced_at' => now()->format('Y-m-d H:i:s'),
            'payment_type' => $paymentData['payment_type'],
            'status_code' => 200,
            'message' => null,
            'request' => $paymentData,
            'parts' => $partResults,
        ]);
    }

    private function buildPaymentDataParts(Order $order): array
    {
        $orderable = $order->orderable;
        $user = $order->user;
        $subscriptionAmount = $this->resolveSubscriptionAmount($order);
        $totalAmount = (float) $order->amount;
        $serviceAmount = max($totalAmount - $subscriptionAmount, 0);

        $basePaymentData = [
            'registration_no' => $this->resolveRegistrationNo($orderable, $user),
            'bank_transaction_id' => $this->resolveBankTransactionId($order),
            'phone_number' => $this->resolvePhoneNumber($orderable, $user),
        ];

        $parts = [];

        if ($subscriptionAmount > 0) {
            $parts[] = [
                ...$basePaymentData,
                'amount' => $this->formatAmount($subscriptionAmount),
                'payment_type' => 'subscription',
                'course_id' => null,
                // A subscription line carries no certificate, so P_PAND_ID is null.
                'pand_id' => null,
                'part' => 'subscription',
            ];
        }

        if ($serviceAmount > 0) {
            $parts[] = [
                ...$basePaymentData,
                'amount' => $this->formatAmount($serviceAmount),
                'payment_type' => $this->resolvePaymentType($orderable),
                'course_id' => $orderable instanceof CourseBooking ? (int) $orderable->course_id : null,
                // Only a certificate payment sends the Oracle certificate number (P_PAND_ID); everything else is null.
                'pand_id' => $this->resolvePandId($orderable),
                'part' => 'service',
            ];
        }

        return $parts;
    }

    private function resolvePandId(mixed $orderable): ?int
    {
        if (! $orderable instanceof CertificateRequest) {
            return null;
        }

        $orderable->loadMissing('certificate');
        $pandId = $orderable->certificate?->pand_id;

        return $pandId !== null ? (int) $pandId : null;
    }

    private function resolvePaymentType(mixed $orderable): ?string
    {
        return match (true) {
            $orderable instanceof MembershipRequest => 'membership',
            $orderable instanceof CertificateRequest => 'certificate',
            $orderable instanceof CourseBooking => 'course',
            $orderable instanceof AdRequest => 'ad',
            $orderable instanceof RestUnitBooking => 'rest_unit',
            $orderable instanceof TravelBooking => 'travel',
            default => null,
        };
    }

    private function resolveRegistrationNo(mixed $orderable, ?User $user): string
    {
        $registrationNo = match (true) {
            $orderable instanceof MembershipRequest => $orderable->registration_number ?: ($user?->reg_number ?? ''),
            default => $user?->reg_number ?? '',
        };

        return $this->normalizeDigits((string) $registrationNo);
    }

    private function resolvePhoneNumber(mixed $orderable, ?User $user): string
    {
        $phone = match (true) {
            $orderable instanceof CertificateRequest => $orderable->phone ?: ($user?->phone ?? ''),
            default => $user?->phone ?? '',
        };

        return $this->normalizeDigits((string) $phone);
    }

    private function resolveBankTransactionId(Order $order): string
    {
        $value = trim((string) ($order->gateway_reference ?: $order->merchant_ref_num ?: ('ORDER-' . $order->id)));

        return $value;
    }

    private function resolveSubscriptionAmount(Order $order): float
    {
        $payloadAmount = data_get($order->payload, 'subscription_charge.amount');
        if (is_numeric($payloadAmount) && (float) $payloadAmount > 0) {
            return min((float) $payloadAmount, (float) $order->amount);
        }

        $pricingItems = collect(data_get($order->payload, 'pricing.items', []))
            ->filter(static fn (mixed $item): bool => is_array($item));

        $pricingAmount = $pricingItems
            ->filter(fn (array $item): bool => $this->isSubscriptionPricingItem((string) ($item['code'] ?? '')))
            ->sum(fn (array $item): float => (float) ($item['amount'] ?? 0));

        if ($pricingAmount > 0) {
            return min((float) $pricingAmount, (float) $order->amount);
        }

        $orderableAmount = data_get($order->orderable, 'subscription_cost');
        if (is_numeric($orderableAmount) && (float) $orderableAmount > 0) {
            return min((float) $orderableAmount, (float) $order->amount);
        }

        return 0.0;
    }

    private function isSubscriptionPricingItem(string $code): bool
    {
        return in_array($code, [
            'subscription_fees',
            'membership_subscription',
            'certificate_subscription',
            'course_subscription',
            'ad_subscription',
        ], true);
    }

    private function summarizePaymentParts(array $paymentParts): array
    {
        if (count($paymentParts) === 1) {
            return $paymentParts[0];
        }

        return [
            'registration_no' => (string) data_get($paymentParts, '0.registration_no', ''),
            'amount' => $this->formatAmount(collect($paymentParts)->sum(fn (array $part): float => (float) $part['amount'])),
            'payment_type' => 'split',
            'bank_transaction_id' => (string) data_get($paymentParts, '0.bank_transaction_id', ''),
            'course_id' => collect($paymentParts)->firstWhere('part', 'service')['course_id'] ?? null,
            'pand_id' => collect($paymentParts)->firstWhere('part', 'service')['pand_id'] ?? null,
            'phone_number' => (string) data_get($paymentParts, '0.phone_number', ''),
            'parts' => $paymentParts,
        ];
    }

    private function resolveMissingField(array $paymentData): ?string
    {
        foreach ([
            'registration_no',
            'amount',
            'payment_type',
            'bank_transaction_id',
            'phone_number',
        ] as $field) {
            if (blank($paymentData[$field] ?? null)) {
                return $field;
            }
        }

        if (($paymentData['payment_type'] ?? null) === 'course' && blank($paymentData['course_id'] ?? null)) {
            return 'course_id';
        }

        return null;
    }

    private function isConfigured(): bool
    {
        return filled(config('services.oracle.host'))
            && filled(config('services.oracle.service_name'))
            && filled(config('services.oracle.username'));
    }

    private function buildSkippedResult(array $paymentData, string $attemptedAt, string $reason, string $message): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'attempted_at' => $attemptedAt,
            'synced_at' => null,
            'payment_type' => $paymentData['payment_type'],
            'status_code' => null,
            'message' => $message,
            'request' => $paymentData,
        ];
    }

    private function logAndReturnResult(Order $order, array $paymentData, array $result): array
    {
        $level = match ($result['status'] ?? null) {
            'success' => 'info',
            'skipped' => 'notice',
            default => 'warning',
        };

        Log::log($level, 'Oracle payment sync result.', [
            ...$this->paymentSyncLogContext($order, $paymentData),
            'result_status' => $result['status'] ?? null,
            'reason' => $result['reason'] ?? null,
            'status_code' => $result['status_code'] ?? null,
            'message' => $result['message'] ?? null,
            'attempted_at' => $result['attempted_at'] ?? null,
            'synced_at' => $result['synced_at'] ?? null,
        ]);

        return $result;
    }

    private function logPaymentSyncEvent(string $message, Order $order, array $paymentData, array $context = []): void
    {
        Log::info($message, [
            ...$this->paymentSyncLogContext($order, $paymentData),
            ...$context,
        ]);
    }

    private function paymentSyncLogContext(Order $order, array $paymentData): array
    {
        return [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'gateway_status' => $order->gateway_status,
            'merchant_ref_num' => $order->merchant_ref_num,
            'gateway_reference' => $order->gateway_reference,
            'orderable_type' => $order->orderable_type,
            'orderable_id' => $order->orderable_id,
            'payment_type' => $paymentData['payment_type'] ?? null,
            'amount' => $paymentData['amount'] ?? null,
            'registration_no' => $this->maskValue($paymentData['registration_no'] ?? null),
            'bank_transaction_id' => $paymentData['bank_transaction_id'] ?? null,
            'course_id' => $paymentData['course_id'] ?? null,
            'pand_id' => $paymentData['pand_id'] ?? null,
            'phone_number' => $this->maskValue($paymentData['phone_number'] ?? null),
        ];
    }

    private function maskValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return str_repeat('*', max(strlen($value) - 4, 0)) . substr($value, -4);
    }

    private function syncWithPdo(PDO $connection, array $paymentData): array
    {
        try {
            $statement = $connection->prepare(self::PAYMENT_SQL);
            $statusCode = '';
            $message = '';

            $statement->bindValue(':p_registration_no', $paymentData['registration_no']);
            $statement->bindValue(':p_amount', $paymentData['amount']);
            $statement->bindValue(':p_payment_type', $paymentData['payment_type']);
            $statement->bindValue(':p_pand_id', $paymentData['pand_id']);
            $statement->bindValue(':p_bank_transaction_id', $paymentData['bank_transaction_id']);
            $statement->bindValue(':p_course_id', $paymentData['course_id']);
            $statement->bindValue(':p_phone_number', $paymentData['phone_number']);
            $statement->bindParam(':p_status_code', $statusCode, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 100);
            $statement->bindParam(':p_message', $message, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 4000);
            $statement->execute();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle payment sync failed. ' . $exception->getMessage(), previous: $exception);
        }

        return [$statusCode, $message];
    }

    /**
     * @param resource|object $connection
     */
    private function syncWithOci8($connection, array $paymentData): array
    {
        $statement = @oci_parse($connection, self::PAYMENT_SQL);

        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle payment sync failed while preparing statement.');
        }

        $statusCode = '';
        $message = '';
        $courseId = $paymentData['course_id'];
        $pandId = $paymentData['pand_id'];

        try {
            $this->bindOciByName($statement, ':p_registration_no', $paymentData['registration_no']);
            $this->bindOciByName($statement, ':p_amount', $paymentData['amount']);
            $this->bindOciByName($statement, ':p_payment_type', $paymentData['payment_type']);
            $this->bindOciByName($statement, ':p_pand_id', $pandId);
            $this->bindOciByName($statement, ':p_bank_transaction_id', $paymentData['bank_transaction_id']);
            $this->bindOciByName($statement, ':p_course_id', $courseId);
            $this->bindOciByName($statement, ':p_phone_number', $paymentData['phone_number']);
            $this->bindOciByName($statement, ':p_status_code', $statusCode, 100);
            $this->bindOciByName($statement, ':p_message', $message, 4000);

            if (! @oci_execute($statement)) {
                $this->throwOciError($statement, 'Oracle payment sync failed during procedure execution.');
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle payment sync failed. ' . $exception->getMessage(), previous: $exception);
        } finally {
            oci_free_statement($statement);
        }

        return [$statusCode, $message];
    }

    /**
     * @param resource|object $statement
     */
    private function bindOciByName($statement, string $parameter, mixed &$value, int $length = -1, int $type = 0): void
    {
        $result = $type === 0
            ? @oci_bind_by_name($statement, $parameter, $value, $length)
            : @oci_bind_by_name($statement, $parameter, $value, $length, $type);

        if (! $result) {
            $this->throwOciError($statement, sprintf('Oracle payment sync failed while binding parameter %s.', $parameter));
        }
    }

    /**
     * @param resource|object $errorSource
     */
    private function throwOciError($errorSource, string $prefix): never
    {
        $error = oci_error($errorSource);
        $message = is_array($error) ? ($error['message'] ?? 'Unknown OCI8 error.') : 'Unknown OCI8 error.';

        throw new RuntimeException(sprintf('%s %s', $prefix, trim((string) $message)));
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function normalizeStatusCode(mixed $statusCode): int|string|null
    {
        if (is_numeric($statusCode)) {
            return (int) $statusCode;
        }

        $statusCode = trim((string) $statusCode);

        return $statusCode !== '' ? $statusCode : null;
    }

    private function isSuccessfulPaymentResponse(array $paymentData, int|string|null $statusCode): bool
    {
        if ($statusCode === 200) {
            return true;
        }

        return ($paymentData['payment_type'] ?? null) === 'subscription'
            && $statusCode === 1;
    }

    private function normalizeDigits(string $value): string
    {
        $normalized = strtr(trim($value), [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);

        return preg_replace('/\D+/', '', $normalized) ?? '';
    }
}
