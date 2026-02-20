<?php

namespace App\Services\Oracle;

use App\Models\RegistrationRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDO;
use Modules\Core\Models\Grade;
use Modules\Core\Models\MedicalUniversity;
use Modules\Core\Models\Nationality;
use Modules\Core\Models\Province;
use RuntimeException;

class OracleDoctorExportService
{
    private const EXPORT_SQL = <<<'SQL'
BEGIN
    EMS_UN_BASIC_DATA.PR_CREATE_DOCTOR(
        P_DOCTORNAME         => :p_doctor_name,
        P_ENG_NAME           => :p_eng_name,
        P_GENDER             => :p_gender,
        P_NATIONALITY_CODE   => :p_nationality_code,
        P_REGISION           => :p_regision,
        P_ID_NO              => :p_id_no,
        P_BIRTHDATE          => TO_DATE(:p_birthdate, 'YYYY-MM-DD'),
        P_BORN_GOV           => :p_born_gov,
        P_DEGREE_CODE        => :p_degree_code,
        P_UNIVERSITY_CODE    => :p_university_code,
        P_GRADUATION_YEAR    => :p_graduation_year,
        P_JOB_LICENSE_NO     => :p_job_license_no,
        P_JOB_LICENSE_DATE   => TO_DATE(:p_job_license_date, 'YYYY-MM-DD'),
        P_GOV_ID             => :p_gov_id,
        P_ADDRESS            => :p_address,
        P_MOBPHONE           => :p_mobphone,
        P_HOMEPHONE1         => :p_homephone1,
        P_EMAIL              => :p_email,
        P_PIC_BLOB           => :p_pic_blob,
        P_REGISTER_NO        => :p_register_no
    );
END;
SQL;

    private const DOCTOR_PIC_TABLE = 'EMS_DOC_PIC.DOCTOR_PIC2';

    public function __construct(
        private readonly OracleConnectionService $oracleConnectionService,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function exportRegistrationRequest(RegistrationRequest $registrationRequest): string
    {
        $payload = $this->buildPayload($registrationRequest);
        $connection = $this->oracleConnectionService->make();

        Log::info('Oracle Connection Driver Debug', [
            'driver' => $connection instanceof PDO ? 'pdo_oci' : 'oci8',
        ]);

        if ($connection instanceof PDO) {
            throw new RuntimeException(
                'Oracle export failed. Image BLOB inside PR_CREATE_DOCTOR requires OCI8 native extension. ' .
                'Please set ORACLE_DRIVER=oci8 and enable OCI8 on PHP runtime.'
            );
        }

        if (! extension_loaded('oci8')) {
            throw new RuntimeException('OCI8 extension is not installed.');
        }

        return $this->exportWithOci8($connection, $payload);
    }

    /**
     * @return array<string, string|int>
     */
    private function buildPayload(RegistrationRequest $registrationRequest): array
    {
        $licenseNumber = (string) $registrationRequest->license_number;
        if (! preg_match('/^\d+$/', $licenseNumber)) {
            throw new RuntimeException('License number must be numeric for Oracle export.');
        }

        $imagePath = $this->resolveImagePath($registrationRequest);
        $imageSize = @filesize($imagePath);

        $gender = match (strtolower((string) $registrationRequest->gender)) {
            'male' => 1,
            'female' => 2,
            default => throw new RuntimeException('Unsupported gender value for Oracle export.'),
        };

        $birthDate = $registrationRequest->birth_date
            ? Carbon::parse($registrationRequest->birth_date)->format('Y-m-d')
            : null;

        $licenseDate = $registrationRequest->license_date
            ? Carbon::parse($registrationRequest->license_date)->format('Y-m-d')
            : null;

        if (blank($birthDate) || blank($licenseDate)) {
            throw new RuntimeException('Birth date and license date are required for Oracle export.');
        }

        $mobileCountryCode = (string) ($registrationRequest->residence_mobile_1_country_code ?? '');
        $mobileNumber = (string) ($registrationRequest->residence_mobile_1 ?? '');
        $mobilePhone = trim($mobileCountryCode . $mobileNumber);

        $addressParts = array_filter([
            $registrationRequest->residence_governorate,
            $registrationRequest->residence_center,
            $registrationRequest->residence_street,
            $registrationRequest->residence_house_number,
        ]);

        $nationalityCode = $this->resolveLookupCode(
            Nationality::class,
            $registrationRequest->nationality,
            'nationality',
        );
        $birthGovernorateCode = $this->resolveLookupCode(
            Province::class,
            $registrationRequest->birth_governorate,
            'birth governorate',
        );
        $gradeCode = $this->resolveLookupCode(
            Grade::class,
            $registrationRequest->grade,
            'grade',
        );
        $universityCode = $this->resolveLookupCode(
            MedicalUniversity::class,
            $registrationRequest->university,
            'university',
        );
        $governorateCode = $this->resolveLookupCode(
            Province::class,
            $registrationRequest->governorate,
            'governorate',
        );

        Log::info('Oracle Doctor Payload Debug', [
            'doctor_name' => (string) $registrationRequest->full_name_ar,
            'eng_name' => (string) $registrationRequest->full_name_en,
            'gender' => $gender,
            'nationality_code' => $nationalityCode,
            'regision' => (int) $registrationRequest->religion,
            'id_no' => (string) $registrationRequest->national_id,
            'birthdate' => $birthDate,
            'born_gov' => $birthGovernorateCode,
            'degree_code' => $gradeCode,
            'university_code' => $universityCode,
            'graduation_year' => (int) $registrationRequest->graduation_year,
            'job_license_no' => (int) $licenseNumber,
            'job_license_date' => $licenseDate,
            'gov_id' => $governorateCode,
            'address' => implode(' - ', $addressParts),
            'mobphone' => $mobilePhone,
            'homephone1' => (string) ($registrationRequest->residence_phone ?? ''),
            'email' => (string) $registrationRequest->email,
            'p_pic_blob_bytes_length' => is_int($imageSize) ? $imageSize : 0,
        ]);

        return [
            'doctor_name' => (string) $registrationRequest->full_name_ar,
            'eng_name' => (string) $registrationRequest->full_name_en,
            'gender' => $gender,
            'nationality_code' => $nationalityCode,
            'regision' => (int) $registrationRequest->religion,
            'id_no' => (string) $registrationRequest->national_id,
            'birthdate' => $birthDate,
            'born_gov' => $birthGovernorateCode,
            'degree_code' => $gradeCode,
            'university_code' => $universityCode,
            'graduation_year' => (int) $registrationRequest->graduation_year,
            'job_license_no' => (int) $licenseNumber,
            'job_license_date' => $licenseDate,
            'gov_id' => $governorateCode,
            'address' => implode(' - ', $addressParts),
            'mobphone' => $mobilePhone,
            'homephone1' => (string) ($registrationRequest->residence_phone ?? ''),
            'email' => (string) $registrationRequest->email,
            'pic_blob_path' => $imagePath,
        ];
    }

    private function resolveLookupCode(string $modelClass, mixed $selectedId, string $label): int
    {
        $id = (int) $selectedId;
        if ($id <= 0) {
            throw new RuntimeException(sprintf('Invalid %s value for Oracle export.', $label));
        }

        $record = $modelClass::query()->find($id);
        if (! $record) {
            throw new RuntimeException(sprintf('%s value not found for Oracle export.', ucfirst($label)));
        }

        $code = data_get($record, 'code');

        // Fallback to record id for backward compatibility when code is not seeded yet.
        return is_numeric($code) ? (int) $code : $id;
    }

    private function resolveImagePath(RegistrationRequest $registrationRequest): string
    {
        $candidatePaths = array_filter([
            (string) data_get($registrationRequest->documents, 'personal_image'),
            (string) $registrationRequest->license_image,
            (string) data_get($registrationRequest->documents, 'license_image'),
        ]);

        foreach ($candidatePaths as $relativePath) {
            $absolutePath = Storage::disk('public')->path($relativePath);
            if (is_file($absolutePath) && is_readable($absolutePath)) {
                return $absolutePath;
            }
        }

        throw new RuntimeException('No image found for Oracle export.');
    }

    /**
     * @param array<string, string|int> $payload
     */
    private function exportWithPdo(PDO $pdo, array $payload): string
    {
        $binaryImage = $this->readImageBinaryPayload($payload);
        $lob = fopen('php://temp', 'r+b');
        if (! is_resource($lob)) {
            throw new RuntimeException('Oracle export failed. Unable to open temporary LOB stream.');
        }

        if (fwrite($lob, $binaryImage) === false) {
            fclose($lob);
            throw new RuntimeException('Oracle export failed. Unable to write binary image into LOB stream.');
        }
        rewind($lob);

        $statement = $pdo->prepare(self::EXPORT_SQL);

        Log::info('Oracle Doctor Blob Bind Debug', array_merge(
            [
                'driver' => 'pdo_oci',
                'parameter' => 'p_pic_blob',
                'transport' => 'binary_lob_stream',
                'p_pic_blob_transport_bytes' => strlen($binaryImage),
                'p_pic_blob_transport_head_hex' => strtoupper(bin2hex(substr($binaryImage, 0, 32))),
            ],
            $this->resolveImageDebugMeta($payload),
        ));

        $statement->bindValue(':p_doctor_name', $payload['doctor_name']);
        $statement->bindValue(':p_eng_name', $payload['eng_name']);
        $statement->bindValue(':p_gender', $payload['gender'], PDO::PARAM_INT);
        $statement->bindValue(':p_nationality_code', $payload['nationality_code'], PDO::PARAM_INT);
        $statement->bindValue(':p_regision', $payload['regision'], PDO::PARAM_INT);
        $statement->bindValue(':p_id_no', $payload['id_no']);
        $statement->bindValue(':p_birthdate', $payload['birthdate']);
        $statement->bindValue(':p_born_gov', $payload['born_gov'], PDO::PARAM_INT);
        $statement->bindValue(':p_degree_code', $payload['degree_code'], PDO::PARAM_INT);
        $statement->bindValue(':p_university_code', $payload['university_code'], PDO::PARAM_INT);
        $statement->bindValue(':p_graduation_year', $payload['graduation_year'], PDO::PARAM_INT);
        $statement->bindValue(':p_job_license_no', $payload['job_license_no'], PDO::PARAM_INT);
        $statement->bindValue(':p_job_license_date', $payload['job_license_date']);
        $statement->bindValue(':p_gov_id', $payload['gov_id'], PDO::PARAM_INT);
        $statement->bindValue(':p_address', $payload['address']);
        $statement->bindValue(':p_mobphone', $payload['mobphone']);
        $statement->bindValue(':p_homephone1', $payload['homephone1']);
        $statement->bindValue(':p_email', $payload['email']);
        $statement->bindParam(':p_pic_blob', $lob, PDO::PARAM_LOB);

        $registerNo = '';
        $statement->bindParam(':p_register_no', $registerNo, PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT, 40);

        $pdo->beginTransaction();
        try {
            $statement->execute();
            if (blank($registerNo)) {
                throw new RuntimeException('Oracle did not return register number.');
            }

            $this->syncDoctorPicWithPdo($pdo, $payload, (string) $registerNo, $binaryImage);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new RuntimeException('Oracle export failed. ' . $exception->getMessage(), previous: $exception);
        } finally {
            if (is_resource($lob)) {
                fclose($lob);
            }
        }

        return (string) $registerNo;
    }

    /**
     * @param resource|object $connection
     * @param array<string, string|int> $payload
     */
    private function exportWithOci8($connection, array $payload): string
    {
        $binaryImage = $this->readImageBinaryPayload($payload);
        $statement = @oci_parse($connection, self::EXPORT_SQL);

        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle export failed while preparing statement.');
        }

        $doctorName = (string) $payload['doctor_name'];
        $engName = (string) $payload['eng_name'];
        $gender = (int) $payload['gender'];
        $nationalityCode = (int) $payload['nationality_code'];
        $regision = (int) $payload['regision'];
        $idNo = (string) $payload['id_no'];
        $birthDate = (string) $payload['birthdate'];
        $bornGov = (int) $payload['born_gov'];
        $degreeCode = (int) $payload['degree_code'];
        $universityCode = (int) $payload['university_code'];
        $graduationYear = (int) $payload['graduation_year'];
        $jobLicenseNo = (int) $payload['job_license_no'];
        $jobLicenseDate = (string) $payload['job_license_date'];
        $govId = (int) $payload['gov_id'];
        $address = (string) $payload['address'];
        $mobilePhone = (string) $payload['mobphone'];
        $homePhone1 = (string) $payload['homephone1'];
        $email = (string) $payload['email'];
        $registerNo = '';

        $this->bindOciByName($statement, ':p_doctor_name', $doctorName);
        $this->bindOciByName($statement, ':p_eng_name', $engName);
        $this->bindOciByName($statement, ':p_gender', $gender);
        $this->bindOciByName($statement, ':p_nationality_code', $nationalityCode);
        $this->bindOciByName($statement, ':p_regision', $regision);
        $this->bindOciByName($statement, ':p_id_no', $idNo);
        $this->bindOciByName($statement, ':p_birthdate', $birthDate);
        $this->bindOciByName($statement, ':p_born_gov', $bornGov);
        $this->bindOciByName($statement, ':p_degree_code', $degreeCode);
        $this->bindOciByName($statement, ':p_university_code', $universityCode);
        $this->bindOciByName($statement, ':p_graduation_year', $graduationYear);
        $this->bindOciByName($statement, ':p_job_license_no', $jobLicenseNo);
        $this->bindOciByName($statement, ':p_job_license_date', $jobLicenseDate);
        $this->bindOciByName($statement, ':p_gov_id', $govId);
        $this->bindOciByName($statement, ':p_address', $address);
        $this->bindOciByName($statement, ':p_mobphone', $mobilePhone);
        $this->bindOciByName($statement, ':p_homephone1', $homePhone1);
        $this->bindOciByName($statement, ':p_email', $email);

        $blobDescriptor = oci_new_descriptor($connection, OCI_D_LOB);
        if ($blobDescriptor === false) {
            oci_free_statement($statement);
            $this->throwOciError($connection, 'Oracle export failed while creating BLOB descriptor.');
        }

        Log::info('Oracle Doctor Blob Bind Debug', array_merge(
            [
                'driver' => 'oci8',
                'parameter' => 'p_pic_blob',
                'transport' => 'oci8_native_lob_in_procedure',
                'p_pic_blob_transport_bytes' => strlen($binaryImage),
                'p_pic_blob_transport_head_hex' => strtoupper(bin2hex(substr($binaryImage, 0, 32))),
            ],
            $this->resolveImageDebugMeta($payload),
        ));

        try {
            if (! $blobDescriptor->writeTemporary($binaryImage, OCI_TEMP_BLOB)) {
                $this->throwOciError($connection, 'Oracle export failed while preparing image blob.');
            }

            $this->bindOciByName($statement, ':p_pic_blob', $blobDescriptor, -1, OCI_B_BLOB);
            $this->bindOciByName($statement, ':p_register_no', $registerNo, 40);

            if (! @oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
                @oci_rollback($connection);
                $this->throwOciError($statement, 'Oracle export failed during procedure execution.');
            }

            if (blank($registerNo)) {
                throw new RuntimeException('Oracle did not return register number.');
            }

            if (! @oci_commit($connection)) {
                @oci_rollback($connection);
                $this->throwOciError($connection, 'Oracle export failed during commit.');
            }
        } catch (\Throwable $exception) {
            @oci_rollback($connection);
            throw new RuntimeException('Oracle export failed. ' . $exception->getMessage(), previous: $exception);
        } finally {
            if (is_object($blobDescriptor) && method_exists($blobDescriptor, 'free')) {
                $blobDescriptor->free();
            }

            oci_free_statement($statement);
        }

        return (string) $registerNo;
    }

    /**
     * @param array<string, string|int> $payload
     */
    private function syncDoctorPicWithPdo(PDO $pdo, array $payload, string $registerNo, string $binaryImage): void
    {
        $doctorId = $this->resolveDoctorPicTargetIdWithPdo($pdo, $payload, $registerNo);

        $updateSql = sprintf('UPDATE %s SET DOCTOR_PIC = :doctor_pic WHERE DOCTOR_ID = :doctor_id', self::DOCTOR_PIC_TABLE);
        $updateStatement = $pdo->prepare($updateSql);
        $updateStatement->bindValue(':doctor_id', $doctorId);
        $updateStatement->bindParam(':doctor_pic', $binaryImage, PDO::PARAM_LOB);
        $updateStatement->execute();

        $operation = 'update';
        if ($updateStatement->rowCount() === 0) {
            $insertSql = sprintf(
                'INSERT INTO %s (DOCTOR_ID, DOCTOR_PIC) VALUES (:doctor_id, :doctor_pic)',
                self::DOCTOR_PIC_TABLE
            );
            $insertStatement = $pdo->prepare($insertSql);
            $insertStatement->bindValue(':doctor_id', $doctorId);
            $insertStatement->bindParam(':doctor_pic', $binaryImage, PDO::PARAM_LOB);
            $insertStatement->execute();
            $operation = 'insert';
        }

        $blobLength = $this->fetchDoctorPicLengthWithPdo($pdo, $doctorId);
        Log::info('Oracle Doctor Pic Sync Debug', [
            'driver' => 'pdo_oci',
            'doctor_id' => $doctorId,
            'operation' => $operation,
            'doctor_pic_length' => $blobLength,
        ]);

        if ($blobLength <= 0) {
            throw new RuntimeException('Oracle export failed. DOCTOR_PIC is empty after direct table sync.');
        }
    }

    /**
     * @param resource|object $connection
     * @param array<string, string|int> $payload
     */
    private function syncDoctorPicWithOci8($connection, array $payload, string $registerNo, string $binaryImage): void
    {
        $doctorId = $this->resolveDoctorPicTargetIdWithOci8($connection, $payload, $registerNo);
        $operation = 'update';

        if (! $this->writeDoctorPicWithOci8($connection, $doctorId, $binaryImage, true)) {
            $this->writeDoctorPicWithOci8($connection, $doctorId, $binaryImage, false);
            $operation = 'insert';
        }

        $blobLength = $this->fetchDoctorPicLengthWithOci8($connection, $doctorId);
        Log::info('Oracle Doctor Pic Sync Debug', [
            'driver' => 'oci8',
            'doctor_id' => $doctorId,
            'operation' => $operation,
            'doctor_pic_length' => $blobLength,
        ]);

        if ($blobLength <= 0) {
            throw new RuntimeException('Oracle export failed. DOCTOR_PIC is empty after direct table sync.');
        }
    }

    /**
     * @param array<string, string|int> $payload
     * @return array<int, string>
     */
    private function resolveDoctorPicIdCandidates(array $payload, string $registerNo): array
    {
        $rawCandidates = [
            $registerNo,
            (string) ($payload['job_license_no'] ?? ''),
            (string) ($payload['id_no'] ?? ''),
        ];

        $candidates = [];
        foreach ($rawCandidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '' || ! preg_match('/^\d+$/', $value)) {
                continue;
            }

            $candidates[$value] = $value;
        }

        return array_values($candidates);
    }

    /**
     * @param array<string, string|int> $payload
     */
    private function resolveDoctorPicTargetIdWithPdo(PDO $pdo, array $payload, string $registerNo): string
    {
        $candidates = $this->resolveDoctorPicIdCandidates($payload, $registerNo);
        if ($candidates === []) {
            throw new RuntimeException('Oracle export failed. Unable to resolve numeric DOCTOR_ID candidate for image sync.');
        }

        foreach ($candidates as $candidate) {
            if ($this->doctorPicRowExistsWithPdo($pdo, $candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function doctorPicRowExistsWithPdo(PDO $pdo, string $doctorId): bool
    {
        $sql = sprintf('SELECT 1 FROM %s WHERE DOCTOR_ID = :doctor_id', self::DOCTOR_PIC_TABLE);
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':doctor_id', $doctorId);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    private function fetchDoctorPicLengthWithPdo(PDO $pdo, string $doctorId): int
    {
        $sql = sprintf(
            'SELECT NVL(DBMS_LOB.GETLENGTH(DOCTOR_PIC), 0) AS PIC_LEN FROM %s WHERE DOCTOR_ID = :doctor_id',
            self::DOCTOR_PIC_TABLE
        );
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':doctor_id', $doctorId);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['PIC_LEN'] ?? 0);
    }

    /**
     * @param resource|object $connection
     * @param array<string, string|int> $payload
     */
    private function resolveDoctorPicTargetIdWithOci8($connection, array $payload, string $registerNo): string
    {
        $candidates = $this->resolveDoctorPicIdCandidates($payload, $registerNo);
        if ($candidates === []) {
            throw new RuntimeException('Oracle export failed. Unable to resolve numeric DOCTOR_ID candidate for image sync.');
        }

        foreach ($candidates as $candidate) {
            if ($this->doctorPicRowExistsWithOci8($connection, $candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * @param resource|object $connection
     */
    private function doctorPicRowExistsWithOci8($connection, string $doctorId): bool
    {
        $sql = sprintf('SELECT 1 FROM %s WHERE DOCTOR_ID = :doctor_id', self::DOCTOR_PIC_TABLE);
        $statement = @oci_parse($connection, $sql);
        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle export failed while preparing DOCTOR_PIC lookup.');
        }

        try {
            $this->bindOciByName($statement, ':doctor_id', $doctorId);
            if (! @oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
                $this->throwOciError($statement, 'Oracle export failed while checking DOCTOR_PIC row.');
            }

            $row = oci_fetch_assoc($statement);
            return is_array($row);
        } finally {
            oci_free_statement($statement);
        }
    }

    /**
     * @param resource|object $connection
     */
    private function fetchDoctorPicLengthWithOci8($connection, string $doctorId): int
    {
        $sql = sprintf(
            'SELECT NVL(DBMS_LOB.GETLENGTH(DOCTOR_PIC), 0) AS PIC_LEN FROM %s WHERE DOCTOR_ID = :doctor_id',
            self::DOCTOR_PIC_TABLE
        );
        $statement = @oci_parse($connection, $sql);
        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle export failed while preparing DOCTOR_PIC length query.');
        }

        try {
            $this->bindOciByName($statement, ':doctor_id', $doctorId);
            if (! @oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
                $this->throwOciError($statement, 'Oracle export failed while querying DOCTOR_PIC length.');
            }

            $row = oci_fetch_assoc($statement);
            return (int) ($row['PIC_LEN'] ?? 0);
        } finally {
            oci_free_statement($statement);
        }
    }

    /**
     * @param resource|object $connection
     */
    private function writeDoctorPicWithOci8($connection, string $doctorId, string $binaryImage, bool $update): bool
    {
        $sql = $update
            ? sprintf(
                'UPDATE %s SET DOCTOR_PIC = EMPTY_BLOB() WHERE DOCTOR_ID = :doctor_id RETURNING DOCTOR_PIC INTO :doctor_pic',
                self::DOCTOR_PIC_TABLE
            )
            : sprintf(
                'INSERT INTO %s (DOCTOR_ID, DOCTOR_PIC) VALUES (:doctor_id, EMPTY_BLOB()) RETURNING DOCTOR_PIC INTO :doctor_pic',
                self::DOCTOR_PIC_TABLE
            );

        $statement = @oci_parse($connection, $sql);
        if ($statement === false) {
            $this->throwOciError($connection, 'Oracle export failed while preparing DOCTOR_PIC sync statement.');
        }

        $lob = oci_new_descriptor($connection, OCI_D_LOB);
        if ($lob === false) {
            oci_free_statement($statement);
            $this->throwOciError($connection, 'Oracle export failed while creating DOCTOR_PIC LOB descriptor.');
        }

        try {
            $this->bindOciByName($statement, ':doctor_id', $doctorId);
            $this->bindOciByName($statement, ':doctor_pic', $lob, -1, OCI_B_BLOB);

            if (! @oci_execute($statement, OCI_NO_AUTO_COMMIT)) {
                $this->throwOciError($statement, 'Oracle export failed while syncing DOCTOR_PIC.');
            }

            if ($update && oci_num_rows($statement) === 0) {
                return false;
            }

            if (! $lob->save($binaryImage)) {
                $this->throwOciError($statement, 'Oracle export failed while writing DOCTOR_PIC blob data.');
            }

            return true;
        } finally {
            if (is_object($lob) && method_exists($lob, 'free')) {
                $lob->free();
            }
            oci_free_statement($statement);
        }
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
            $this->throwOciError($statement, sprintf('Oracle export failed while binding parameter %s.', $parameter));
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

    /**
     * @param array<string, string|int> $payload
     */
    private function resolveImagePathFromPayload(array $payload): string
    {
        $path = (string) ($payload['pic_blob_path'] ?? '');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Image file is missing or unreadable for Oracle export.');
        }

        return $path;
    }

    /**
     * @param array<string, string|int> $payload
     */
    private function readImageBinaryPayload(array $payload): string
    {
        $path = $this->resolveImagePathFromPayload($payload);
        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Oracle export failed. Unable to read image content.');
        }

        return $this->normalizeImageBinaryForOracle($binary);
    }

    private function normalizeImageBinaryForOracle(string $binary): string
    {
        if ($this->isJpegBinary($binary)) {
            return $binary;
        }

        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException(
                'Oracle export failed. Source image is not JPEG and GD extension is required for conversion.'
            );
        }

        $sourceImage = @imagecreatefromstring($binary);
        if ($sourceImage === false) {
            throw new RuntimeException('Oracle export failed. Unsupported source image format for JPEG conversion.');
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($sourceImage);
            throw new RuntimeException('Oracle export failed. Invalid source image dimensions.');
        }

        $jpegCanvas = imagecreatetruecolor($width, $height);
        if ($jpegCanvas === false) {
            imagedestroy($sourceImage);
            throw new RuntimeException('Oracle export failed. Unable to allocate JPEG canvas.');
        }

        $white = imagecolorallocate($jpegCanvas, 255, 255, 255);
        imagefilledrectangle($jpegCanvas, 0, 0, $width, $height, $white);
        imagecopy($jpegCanvas, $sourceImage, 0, 0, 0, 0, $width, $height);

        ob_start();
        $encoded = imagejpeg($jpegCanvas, null, 90);
        $jpegBinary = ob_get_clean();

        imagedestroy($sourceImage);
        imagedestroy($jpegCanvas);

        if (! $encoded || ! is_string($jpegBinary) || $jpegBinary === '' || ! $this->isJpegBinary($jpegBinary)) {
            throw new RuntimeException('Oracle export failed. JPEG conversion produced invalid binary.');
        }

        return $jpegBinary;
    }

    private function isJpegBinary(string $binary): bool
    {
        return strlen($binary) >= 3 && substr($binary, 0, 3) === "\xFF\xD8\xFF";
    }

    /**
     * @param array<string, string|int> $payload
     * @return array<string, string|int|null>
     */
    private function resolveImageDebugMeta(array $payload): array
    {
        $path = $this->resolveImagePathFromPayload($payload);
        $size = @filesize($path);
        $sha256 = @hash_file('sha256', $path) ?: null;
        $headHex = '';

        $stream = @fopen($path, 'rb');
        if (is_resource($stream)) {
            $headBytes = fread($stream, 32);
            if ($headBytes !== false) {
                $headHex = strtoupper(bin2hex($headBytes));
            }
            fclose($stream);
        }

        return [
            'image_path' => $path,
            'p_pic_blob_bytes_length' => is_int($size) ? $size : 0,
            'p_pic_blob_sha256' => $sha256,
            'p_pic_blob_head_hex' => $headHex,
        ];
    }
}
