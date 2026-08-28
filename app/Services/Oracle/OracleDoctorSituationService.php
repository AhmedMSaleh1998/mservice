<?php

namespace App\Services\Oracle;

use App\Support\NationalIdentifier;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;

class OracleDoctorSituationService
{
    private const CHECK_DOCTOR_SITUATION_SQL = <<<'SQL'
        BEGIN
            EMS_UN_BASIC_DATA.ITP_MOBILE_API.CHECK_DOCTOR_SITUATION(
                P_REGISTER_NO => :p_register_no,
                P_EXISTS => :p_exists
            );
        END;
    SQL;

    public function __construct(
        private readonly OracleConnectionService $oracleConnectionService,
    ) {
    }

    /**
     * Whether the syndicate has an open situation (موقف) recorded against the doctor.
     */
    public function doctorHasSituation(string $registerNo): bool
    {
        $normalizedRegisterNo = NationalIdentifier::normalize($registerNo);
        $connection = $this->oracleConnectionService->make();
        $driver = $connection instanceof PDO ? 'pdo_oci' : 'oci8';
        $logContext = [
            'driver' => $driver,
            'register_no' => $normalizedRegisterNo,
        ];

        Log::info('Oracle doctor situation lookup started.', $logContext);

        try {
            $situationFlag = $connection instanceof PDO
                ? $this->lookupWithPdo($connection, $normalizedRegisterNo)
                : $this->lookupWithOci8($connection, $normalizedRegisterNo);
        } catch (RuntimeException $exception) {
            Log::error('Oracle doctor situation lookup failed.', [
                ...$logContext,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $normalizedFlag = strtoupper(trim($situationFlag));

        Log::info('Oracle doctor situation lookup completed.', [
            ...$logContext,
            'situation_flag' => $normalizedFlag,
        ]);

        return match ($normalizedFlag) {
            'Y' => true,
            'N' => false,
            default => throw new RuntimeException('Oracle doctor situation lookup returned an unexpected result.'),
        };
    }

    private function lookupWithPdo(PDO $connection, string $registerNo): string
    {
        try {
            $statement = $connection->prepare(self::CHECK_DOCTOR_SITUATION_SQL);
            $situationFlag = '';
            $statement->bindValue(':p_register_no', $registerNo);
            $statement->bindParam(':p_exists', $situationFlag, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 10);
            $statement->execute();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle doctor situation lookup failed. ' . $exception->getMessage(), previous: $exception);
        }

        return $situationFlag;
    }

    /**
     * @param resource|object $connection
     */
    private function lookupWithOci8($connection, string $registerNo): string
    {
        $statement = @oci_parse($connection, self::CHECK_DOCTOR_SITUATION_SQL);

        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle doctor situation lookup failed while preparing statement.');
        }

        $situationFlag = '';

        try {
            if (! @oci_bind_by_name($statement, ':p_register_no', $registerNo)) {
                $this->throwOciError($statement, 'Oracle doctor situation lookup failed while binding parameter :p_register_no.');
            }

            if (! @oci_bind_by_name($statement, ':p_exists', $situationFlag, 10)) {
                $this->throwOciError($statement, 'Oracle doctor situation lookup failed while binding parameter :p_exists.');
            }

            if (! @oci_execute($statement)) {
                $this->throwOciError($statement, 'Oracle doctor situation lookup failed during procedure execution.');
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('Oracle doctor situation lookup failed. ' . $exception->getMessage(), previous: $exception);
        } finally {
            oci_free_statement($statement);
        }

        return $situationFlag;
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
