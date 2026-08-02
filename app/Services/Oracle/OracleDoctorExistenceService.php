<?php

namespace App\Services\Oracle;

use PDO;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OracleDoctorExistenceService
{
    private const CHECK_DOCTOR_EXIST_SQL = <<<'SQL'
        BEGIN
            EMS_UN_BASIC_DATA.ITP_MOBILE_API.CHK_DOCTOR_EXIST(
                P_REGISTER_NO => :p_register_no,
                P_ID_NO => :p_id_no,
                P_DOCTOR_YN => :p_doctor_yn
            );
        END;
    SQL;

    public function __construct(
        private readonly OracleConnectionService $oracleConnectionService,
    ) {
    }

    public function doctorExists(string $registerNo, string $idNo): bool
    {
        $normalizedRegisterNo = $this->normalizeLookupValue($registerNo);
        $normalizedIdNo = $this->normalizeLookupValue($idNo);
        $connection = $this->oracleConnectionService->make();
        $driver = $connection instanceof PDO ? 'pdo_oci' : 'oci8';
        $logContext = $this->buildLookupLogContext(
            $driver,
            $registerNo,
            $idNo,
            $normalizedRegisterNo,
            $normalizedIdNo,
        );

        Log::info('Oracle doctor lookup started.', $logContext);

        try {
            $doctorFlag = $connection instanceof PDO
                ? $this->doctorExistsWithPdo($connection, $normalizedRegisterNo, $normalizedIdNo)
                : $this->doctorExistsWithOci8($connection, $normalizedRegisterNo, $normalizedIdNo);
        } catch (RuntimeException $exception) {
            Log::error('Oracle doctor lookup failed.', [
                ...$logContext,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $normalizedDoctorFlag = strtoupper(trim($doctorFlag));

        Log::info('Oracle doctor lookup completed.', [
            ...$logContext,
            'doctor_flag' => $normalizedDoctorFlag,
        ]);

        $doctorExists = $this->normalizeDoctorExistsFlag($doctorFlag);

        if (! $doctorExists) {
            Log::warning('Oracle doctor lookup returned not found.', [
                ...$logContext,
                'doctor_flag' => $normalizedDoctorFlag,
            ]);
        }

        return $doctorExists;
    }

    private function doctorExistsWithPdo(PDO $connection, string $registerNo, string $idNo): string
    {
        try {
            $statement = $connection->prepare(self::CHECK_DOCTOR_EXIST_SQL);
            $doctorFlag = '';

            $statement->bindValue(':p_register_no', $registerNo);
            $statement->bindValue(':p_id_no', $idNo);
            $statement->bindParam(':p_doctor_yn', $doctorFlag, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 10);
            $statement->execute();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle doctor lookup failed. ' . $exception->getMessage(), previous: $exception);
        }

        return $doctorFlag;
    }

    /**
     * @param resource|object $connection
     */
    private function doctorExistsWithOci8($connection, string $registerNo, string $idNo): string
    {
        $statement = @oci_parse($connection, self::CHECK_DOCTOR_EXIST_SQL);

        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle doctor lookup failed while preparing statement.');
        }

        $doctorFlag = '';

        try {
            $this->bindOciByName($statement, ':p_register_no', $registerNo);
            $this->bindOciByName($statement, ':p_id_no', $idNo);
            $this->bindOciByName($statement, ':p_doctor_yn', $doctorFlag, 10);

            if (! @oci_execute($statement)) {
                $this->throwOciError($statement, 'Oracle doctor lookup failed during procedure execution.');
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle doctor lookup failed. ' . $exception->getMessage(), previous: $exception);
        } finally {
            oci_free_statement($statement);
        }

        return $doctorFlag;
    }

    private function normalizeDoctorExistsFlag(string $doctorFlag): bool
    {
        return match (strtoupper(trim($doctorFlag))) {
            'Y' => true,
            'N' => false,
            default => throw new RuntimeException('Oracle doctor lookup returned an unexpected result.'),
        };
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

    private function buildLookupLogContext(
        string $driver,
        string $registerNo,
        string $idNo,
        string $normalizedRegisterNo,
        string $normalizedIdNo,
    ): array {
        $trimmedRegisterNo = trim($registerNo);
        $trimmedIdNo = trim($idNo);

        return [
            'driver' => $driver,
            'register_no_input' => $trimmedRegisterNo,
            'register_no_normalized' => $normalizedRegisterNo,
            'register_no_changed' => $trimmedRegisterNo !== $normalizedRegisterNo,
            'id_no_input' => $this->maskNationalId($trimmedIdNo),
            'id_no_normalized' => $this->maskNationalId($normalizedIdNo),
            'id_no_changed' => $trimmedIdNo !== $normalizedIdNo,
        ];
    }

    private function maskNationalId(string $idNo): string
    {
        if ($idNo === '') {
            return '';
        }

        return str_repeat('*', max(strlen($idNo) - 4, 0)) . substr($idNo, -4);
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
            $this->throwOciError($statement, sprintf('Oracle doctor lookup failed while binding parameter %s.', $parameter));
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
