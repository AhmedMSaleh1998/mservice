<?php

namespace Modules\Users\Services;

use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Models\RegistrationRequest;

class NewRegisterService
{
    public function register(NewRegisterDTO $dto)
    {
        $registerRequest = RegistrationRequest::create([
            'phone' => $dto->phone,
            'national_id' => $dto->nationalId,
            'full_name_ar' => $dto->fullNameAr,
            'full_name_en' => $dto->fullNameEn,
            'gender' => $dto->gender,
            'nationality' => $dto->nationality,
            'religion' => $dto->religion,
            'governorate' => $dto->governorate,
            'issued_from' => $dto->issuedFrom,
            'birth_governorate' => $dto->birthGovernorate,
            'birth_date' => $dto->birthDate,
            'residence_governorate' => $dto->residenceGovernorate,
            'residence_center' => $dto->residenceCenter,
            'residence_street' => $dto->residenceStreet,
            'residence_house_number' => $dto->residenceHouseNumber,
            'residence_phone' => $dto->residencePhone,
            'residence_mobile_1' => $dto->residenceMobile1,
            'residence_mobile_2' => $dto->residenceMobile2,
            'email' => $dto->email,
            'university' => $dto->university,
            'faculty' => $dto->faculty,
            'graduation_year' => $dto->graduationYear,
            'graduation_month' => $dto->graduationMonth,
            'grade' => $dto->grade,
            'first_foreign_language' => $dto->firstForeignLanguage,
            'second_foreign_language' => $dto->secondForeignLanguage,
            'active' => false,
            'reg_code' => 'EMS' . random_int(11111, 99999),
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
