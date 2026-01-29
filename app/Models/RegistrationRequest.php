<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    protected $fillable = [
        'national_id',
        'full_name_ar',
        'full_name_en',
        'gender',
        'nationality',
        'religion',
        'governorate',
        'issued_from',
        'birth_governorate',
        'birth_date',
        'residence_governorate',
        'residence_center',
        'residence_street',
        'residence_house_number',
        'residence_phone',
        'residence_mobile_1',
        'residence_mobile_2',
        'email',
        'university',
        'faculty',
        'graduation_year',
        'graduation_month',
        'grade',
        'first_foreign_language',
        'second_foreign_language',
        'active',
        'documents',
        'reg_code',
    ];


    protected $casts = [
        'documents' => 'array',
        'active' => 'boolean',
        'birth_date' => 'date',
    ];

    // Helper method to get all document types
    public function getDocumentTypes(): array
    {
        return [
            'personal_image' => 'Personal Photo',
            'national_id_image' => 'National ID Photo',
            'graduation_certificate_image' => 'Graduation Certificate',
            'internship_certificate_image' => 'Internship Certificate',
            'criminal_record_certificate_image' => 'Criminal Record Certificate',
            'dob_image' => 'Date of Birth Certificate',
        ];
    }
}
