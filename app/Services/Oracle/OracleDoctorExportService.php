<?php

namespace App\Services\Oracle;

use App\Models\RegistrationRequest;
use Carbon\Carbon;
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

        if ($connection instanceof PDO) {
            return $this->exportWithPdo($connection, $payload);
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

        $imageContent = $this->resolveImageContent($registrationRequest);

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
        $mobilePhone = trim($mobileCountryCode . ' ' . $mobileNumber);

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
            'pic_base64' => base64_encode($imageContent),
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

    private function resolveImageContent(RegistrationRequest $registrationRequest): string
    {
        $candidatePaths = array_filter([
            (string) data_get($registrationRequest->documents, 'personal_image'),
            (string) $registrationRequest->license_image,
            (string) data_get($registrationRequest->documents, 'license_image'),
        ]);

        foreach ($candidatePaths as $relativePath) {
            $absolutePath = Storage::disk('public')->path($relativePath);
            if (! is_file($absolutePath)) {
                continue;
            }

            $content = file_get_contents($absolutePath);
            if ($content !== false && $content !== '') {
                return $content;
            }
        }

        throw new RuntimeException('No image found for Oracle export.');
    }

    /**
     * @param array<string, string|int> $payload
     */
    private function exportWithPdo(PDO $pdo, array $payload): string
    {
        $binaryImage = $this->decodeImagePayload($payload);
        $statement = $pdo->prepare(self::EXPORT_SQL);

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
        $statement->bindValue(':p_pic_blob', $binaryImage, PDO::PARAM_LOB);

        $registerNo = null;
        $statement->bindParam(':p_register_no', $registerNo, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT, 40);

        $pdo->beginTransaction();
        try {
            $statement->execute();
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw new RuntimeException('Oracle export failed. ' . $exception->getMessage(), previous: $exception);
        }

        if (blank($registerNo)) {
            throw new RuntimeException('Oracle did not return register number.');
        }

        return (string) $registerNo;
    }

    /**
     * @param resource|object $connection
     * @param array<string, string|int> $payload
     */
    private function exportWithOci8($connection, array $payload): string
    {
        $binaryImage = $this->decodeImagePayload($payload);
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

        if (blank($registerNo)) {
            throw new RuntimeException('Oracle did not return register number.');
        }

        return (string) $registerNo;
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
    private function decodeImagePayload(array $payload): string
    {
        $binaryImage = base64_decode((string) $payload['pic_base64'], true);
        if ($binaryImage === false || $binaryImage === '') {
            throw new RuntimeException('Invalid base64 image payload for Oracle export.');
        }

        return $binaryImage;
    }
}
