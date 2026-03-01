<?php

namespace Modules\Users\Services;

use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Models\RegistrationRequest;

class NewRegisterService
{
    public function register(NewRegisterDTO $dto)
    {
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

        $documents = $this->uploadDocuments($registerRequest->id, $dto->getDocuments());
        $registerRequest->documents = $documents;
        $registerRequest->save();
        return $registerRequest->fresh();
    }

    private function uploadDocuments(int $id, array $documents): array
    {
        $uploadedPaths = [];
        foreach ($documents as $key => $file) {
            if (is_null($file)) {
                continue;
            }
            $filename = time() . "_{$key}_" . $file->getClientOriginalName();
            $path = $file->storeAs("documents/{$id}", $filename, 'public');
            $uploadedPaths[$key] = $path;
        }
        return $uploadedPaths;
    }
}
