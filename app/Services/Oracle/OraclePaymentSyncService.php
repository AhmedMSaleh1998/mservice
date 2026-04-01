<?php

namespace App\Services\Oracle;

use Illuminate\Support\Facades\Log;
use Modules\Ads\Models\AdRequest;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
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

        $paymentData = $this->buildPaymentData($order);
        $attemptedAt = now()->format('Y-m-d H:i:s');

        if (! config('services.oracle.payment_sync_enabled', true)) {
            return $this->buildSkippedResult($paymentData, $attemptedAt, 'disabled', 'Oracle payment sync is disabled.');
        }

        if (! $this->isConfigured()) {
            return $this->buildSkippedResult($paymentData, $attemptedAt, 'not_configured', 'Oracle payment sync is not configured.');
        }

        if ($paymentData['payment_type'] === null) {
            return $this->buildSkippedResult($paymentData, $attemptedAt, 'unsupported_payment_type', 'This paid order type is not supported by Oracle PAYMENT_SERVICE.');
        }

        if ((float) $paymentData['amount'] <= 0) {
            return $this->buildSkippedResult($paymentData, $attemptedAt, 'zero_amount', 'Zero-amount payments are not synced to Oracle.');
        }

        $missingField = $this->resolveMissingField($paymentData);
        if ($missingField !== null) {
            return [
                'status' => 'failed',
                'reason' => 'missing_required_data',
                'attempted_at' => $attemptedAt,
                'synced_at' => null,
                'payment_type' => $paymentData['payment_type'],
                'status_code' => null,
                'message' => sprintf('Oracle payment sync is missing required field: %s.', $missingField),
                'request' => $paymentData,
            ];
        }

        $connection = $this->oracleConnectionService->make();
        $driver = $connection instanceof PDO ? 'pdo_oci' : 'oci8';

        Log::info('Oracle payment sync started.', [
            'driver' => $driver,
            'order_id' => $order->id,
            'payment_type' => $paymentData['payment_type'],
        ]);

        try {
            [$statusCode, $message] = $connection instanceof PDO
                ? $this->syncWithPdo($connection, $paymentData)
                : $this->syncWithOci8($connection, $paymentData);
        } catch (RuntimeException $exception) {
            Log::error('Oracle payment sync failed.', [
                'driver' => $driver,
                'order_id' => $order->id,
                'payment_type' => $paymentData['payment_type'],
                'error' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'reason' => 'oracle_error',
                'attempted_at' => $attemptedAt,
                'synced_at' => null,
                'payment_type' => $paymentData['payment_type'],
                'status_code' => null,
                'message' => $exception->getMessage(),
                'request' => $paymentData,
            ];
        }

        $normalizedStatusCode = $this->normalizeStatusCode($statusCode);
        $normalizedMessage = trim($message);
        $success = $normalizedStatusCode === 200;

        Log::log($success ? 'info' : 'warning', 'Oracle payment sync completed.', [
            'driver' => $driver,
            'order_id' => $order->id,
            'payment_type' => $paymentData['payment_type'],
            'status_code' => $normalizedStatusCode,
            'message' => $normalizedMessage,
        ]);

        return [
            'status' => $success ? 'success' : 'failed',
            'reason' => $success ? null : 'oracle_rejected',
            'attempted_at' => $attemptedAt,
            'synced_at' => $success ? now()->format('Y-m-d H:i:s') : null,
            'payment_type' => $paymentData['payment_type'],
            'status_code' => $normalizedStatusCode,
            'message' => $normalizedMessage !== '' ? $normalizedMessage : null,
            'request' => $paymentData,
        ];
    }

    private function buildPaymentData(Order $order): array
    {
        $orderable = $order->orderable;
        $user = $order->user;

        return [
            'registration_no' => $this->resolveRegistrationNo($orderable, $user),
            'amount' => $this->formatAmount($order->amount),
            'payment_type' => $this->resolvePaymentType($orderable),
            'bank_transaction_id' => $this->resolveBankTransactionId($order),
            'course_id' => $orderable instanceof CourseBooking ? (int) $orderable->course_id : null,
            'phone_number' => $this->resolvePhoneNumber($orderable, $user),
        ];
    }

    private function resolvePaymentType(mixed $orderable): ?string
    {
        return match (true) {
            $orderable instanceof MembershipRequest => 'subscription',
            $orderable instanceof CertificateRequest => 'certificate',
            $orderable instanceof CourseBooking => 'course',
            $orderable instanceof AdRequest => null,
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

    private function syncWithPdo(PDO $connection, array $paymentData): array
    {
        try {
            $statement = $connection->prepare(self::PAYMENT_SQL);
            $statusCode = '';
            $message = '';

            $statement->bindValue(':p_registration_no', $paymentData['registration_no']);
            $statement->bindValue(':p_amount', $paymentData['amount']);
            $statement->bindValue(':p_payment_type', $paymentData['payment_type']);
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

        try {
            $this->bindOciByName($statement, ':p_registration_no', $paymentData['registration_no']);
            $this->bindOciByName($statement, ':p_amount', $paymentData['amount']);
            $this->bindOciByName($statement, ':p_payment_type', $paymentData['payment_type']);
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
