<?php

namespace Modules\Users\Dto;

use Illuminate\Http\UploadedFile;

class NewRegisterDTO
{
    public function __construct(
        public readonly string $nationalId,
        public readonly string $fullNameAr,
        public readonly string $fullNameEn,
        public readonly string $gender,
        public readonly int $nationalityId,
        public readonly string $religion,
        public readonly int $governorateId,
        public readonly string $issuedFrom,
        public readonly int $birthGovernorateId,
        public readonly string $birthDate,
        public readonly int $residenceGovernorateId,
        public readonly string $residenceCenter,
        public readonly string $residenceStreet,
        public readonly string $residenceHouseNumber,
        public readonly string $residencePhone,
        public readonly string $residenceMobile1,
        public readonly string $residenceMobile2,
        public readonly string $email,
        public readonly int $universityId,
        public readonly string $faculty,
        public readonly string $graduationYear,
        public readonly string $graduationMonth,
        public readonly int $gradeId,
        public readonly int $firstForeignLanguageId,
        public readonly ?int $secondForeignLanguageId,
        public readonly UploadedFile $personalImg,
        public readonly UploadedFile $nationalIdImg,
        public readonly UploadedFile $graduationCertificateImg,
        public readonly UploadedFile $internshipCertificateImg,
        public readonly UploadedFile $criminalRecordCertificateImg,
        public readonly UploadedFile $dobImg,
    )
    {
    }

    public static function fromRequest($request): self
    {
        return new self(
            $request->input('national_id'),
            $request->input('full_name_ar'),
            $request->input('full_name_en'),
            $request->input('gender'),
            (int) $request->input('nationality'),
            $request->input('religion'),
            (int) $request->input('governorate'),
            $request->input('issued_from'),
            (int) $request->input('birth_governorate'),
            $request->input('birth_date'),
            (int) $request->input('residence_governorate'),
            $request->input('residence_center'),
            $request->input('residence_street'),
            $request->input('residence_house_number'),
            $request->input('residence_phone'),
            $request->input('residence_mobile_1'),
            $request->input('residence_mobile_2'),
            $request->input('email'),
            (int) $request->input('university'),
            $request->input('faculty'),
            $request->input('graduation_year'),
            $request->input('graduation_month'),
            (int) $request->input('grade'),
            (int) $request->input('first_foreign_language'),
            $request->filled('second_foreign_language')
                ? (int) $request->input('second_foreign_language')
                : null,
            $request->file('personal_image'),
            $request->file('national_id_image'),
            $request->file('graduation_certificate_image'),
            $request->file('internship_certificate_image'),
            $request->file('criminal_record_certificate_image'),
            $request->file('dob_image'),
        );
    }

    public function getDocuments(): array
    {
        return [
            'personal_image' => $this->personalImg,
            'national_id_image' => $this->nationalIdImg,
            'graduation_certificate_image' => $this->graduationCertificateImg,
            'internship_certificate_image' => $this->internshipCertificateImg,
            'criminal_record_certificate_image' => $this->criminalRecordCertificateImg,
            'dob_image' => $this->dobImg,
        ];
    }
}
