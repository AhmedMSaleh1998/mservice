<?php

namespace App\Services\Oracle;

use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;

class OracleDoctorDataLookupService
{
    private const LOOKUP_SQL = <<<'SQL'
        BEGIN
            EMS_UN_BASIC_DATA.ITP_MOBILE_API.GET_DOCTOR_DATA_REGNO(
                P_REGISTER_NO => :p_register_no,
                P_RESULT => :p_result
            );
        END;
    SQL;

    public function __construct(
        private readonly OracleConnectionService $oracleConnectionService,
    ) {
    }

    public function findByRegisterNo(?string $registerNo): ?array
    {
        if (! config('services.oracle.register_lookup_enabled', true)) {
            return null;
        }

        $normalizedRegisterNo = $this->normalizeLookupValue((string) $registerNo);
        if ($normalizedRegisterNo === '') {
            return null;
        }

        $connection = $this->oracleConnectionService->make();
        $driver = $connection instanceof PDO ? 'pdo_oci' : 'oci8';

        Log::info('Oracle doctor data lookup started.', [
            'driver' => $driver,
            'register_no' => $normalizedRegisterNo,
        ]);

        try {
            $rawResult = $connection instanceof PDO
                ? $this->lookupWithPdo($connection, $normalizedRegisterNo)
                : $this->lookupWithOci8($connection, $normalizedRegisterNo);
        } catch (RuntimeException $exception) {
            Log::error('Oracle doctor data lookup failed.', [
                'driver' => $driver,
                'register_no' => $normalizedRegisterNo,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $doctorData = $this->decodeResult($rawResult);

        Log::info('Oracle doctor data lookup completed.', [
            'driver' => $driver,
            'register_no' => $normalizedRegisterNo,
            'found' => $doctorData !== null,
        ]);

        return $doctorData;
    }

    private function lookupWithPdo(PDO $connection, string $registerNo): string
    {
        try {
            $statement = $connection->prepare(self::LOOKUP_SQL);
            $result = '';

            $statement->bindValue(':p_register_no', $registerNo);
            $statement->bindParam(':p_result', $result, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 32767);
            $statement->execute();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle doctor data lookup failed. ' . $exception->getMessage(), previous: $exception);
        }

        return $result;
    }

    /**
     * @param resource|object $connection
     */
    private function lookupWithOci8($connection, string $registerNo): string
    {
        $statement = @oci_parse($connection, self::LOOKUP_SQL);

        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle doctor data lookup failed while preparing statement.');
        }

        $result = '';

        try {
            $this->bindOciByName($statement, ':p_register_no', $registerNo);
            $this->bindOciByName($statement, ':p_result', $result, 32767);

            if (! @oci_execute($statement)) {
                $this->throwOciError($statement, 'Oracle doctor data lookup failed during procedure execution.');
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle doctor data lookup failed. ' . $exception->getMessage(), previous: $exception);
        } finally {
            oci_free_statement($statement);
        }

        return $result;
    }

    private function decodeResult(string $result): ?array
    {
        $payload = trim($result);
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Oracle doctor data lookup returned an invalid JSON payload.');
        }

        if (array_is_list($decoded)) {
            $decoded = $decoded[0] ?? null;
        }

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        return [
            'id' => $this->normalizeNullableInt($decoded['ID'] ?? null),
            'register_no' => $this->normalizeNullableString($decoded['REGISTER_NO'] ?? null),
            'doctor_name' => $this->normalizeNullableString($decoded['DOCTORNAME'] ?? null),
            'specialization_arabic_name' => $this->normalizeNullableString($decoded['SPECIALIZATION_ARABIC_NAME'] ?? null),
            'consult_id' => $this->normalizeNullableInt($decoded['CONSULT_ID'] ?? null),
            'consult_name' => $this->normalizeNullableString($decoded['CONSULT_NAME'] ?? null),
        ];
    }

    private function normalizeLookupValue(string $value): string
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

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
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
            $this->throwOciError($statement, sprintf('Oracle doctor data lookup failed while binding parameter %s.', $parameter));
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
}
