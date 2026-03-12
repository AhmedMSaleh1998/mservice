<?php

namespace App\Services\Oracle;

use PDO;
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
        $connection = $this->oracleConnectionService->make();

        return $connection instanceof PDO
            ? $this->doctorExistsWithPdo($connection, $registerNo, $idNo)
            : $this->doctorExistsWithOci8($connection, $registerNo, $idNo);
    }

    private function doctorExistsWithPdo(PDO $connection, string $registerNo, string $idNo): bool
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

        return $this->normalizeDoctorExistsFlag($doctorFlag);
    }

    /**
     * @param resource|object $connection
     */
    private function doctorExistsWithOci8($connection, string $registerNo, string $idNo): bool
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

        return $this->normalizeDoctorExistsFlag($doctorFlag);
    }

    private function normalizeDoctorExistsFlag(string $doctorFlag): bool
    {
        return match (strtoupper(trim($doctorFlag))) {
            'Y' => true,
            'N' => false,
            default => throw new RuntimeException('Oracle doctor lookup returned an unexpected result.'),
        };
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
