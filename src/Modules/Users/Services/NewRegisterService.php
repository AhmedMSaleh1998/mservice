<?php

namespace Modules\Users\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Models\RegistrationRequest;
use RuntimeException;
use Throwable;

class NewRegisterService
{
    public function register(NewRegisterDTO $dto)
    {
        $documentsDirectory = null;

        try {
            $registerRequest = DB::transaction(function () use ($dto, &$documentsDirectory) {
                $registerRequest = RegistrationRequest::create([
                    'national_id' => $dto->nationalId,
                    'full_name_ar' => $dto->fullNameAr,
                    'full_name_en' => $dto->fullNameEn,
                    'gender' => $dto->gender,
                    'nationality' => $dto->nationalityId,
                    'religion' => $dto->religionId,
                    'governorate' => $dto->governorateId,
                    'issued_from' => $dto->issuedFrom,
                    'birth_governorate' => $dto->birthGovernorateId,
                    'birth_date' => $dto->birthDate,
                    'residence_governorate' => $dto->residenceGovernorateId,
                    'residence_center' => $dto->residenceCenter,
                    'residence_street' => $dto->residenceStreet,
                    'residence_house_number' => $dto->residenceHouseNumber,
                    'residence_phone' => $dto->residencePhone,
                    'residence_mobile_1_country_code' => $dto->residenceMobile1CountryCode,
                    'residence_mobile_1' => $dto->residenceMobile1,
                    'residence_mobile_2_country_code' => $dto->residenceMobile2CountryCode,
                    'residence_mobile_2' => $dto->residenceMobile2,
                    'email' => $dto->email,
                    'university' => $dto->universityId,
                    'faculty' => $dto->faculty,
                    'graduation_year' => $dto->graduationYear,
                    'graduation_month' => $dto->graduationMonth,
                    'grade' => $dto->gradeId,
                    'first_foreign_language' => $dto->firstForeignLanguageId,
                    'second_foreign_language' => $dto->secondForeignLanguageId,
                    'status' => RegistrationRequest::STATUS_PENDING_REVIEW,
                    'active' => false,
                ]);

                $documentsDirectory = "documents/{$registerRequest->id}";
                $registerRequest->documents = $this->uploadDocuments($documentsDirectory, $dto->getDocuments());
                $registerRequest->save();

                return $registerRequest;
            });
        } catch (Throwable $exception) {
            if ($documentsDirectory !== null) {
                Storage::disk('public')->deleteDirectory($documentsDirectory);
            }

            throw $exception;
        }

        return $registerRequest->fresh();
    }

    private function uploadDocuments(string $directory, array $documents): array
    {
        $uploadedPaths = [];

        foreach ($documents as $key => $file) {
            if (is_null($file)) {
                continue;
            }

            $filename = time() . "_{$key}_" . $file->getClientOriginalName();
            $path = $file->storeAs($directory, $filename, 'public');

            if ($path === false) {
                throw new RuntimeException("Failed to store document [{$key}].");
            }

            $uploadedPaths[$key] = $path;
        }

        return $uploadedPaths;
    }
}
