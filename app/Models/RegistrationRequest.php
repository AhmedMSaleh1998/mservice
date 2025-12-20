<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    protected $fillable = [
        'phone',
        'national_id',
        'active',
        'documents',
        'reg_code',
    ];


    protected $casts = [
        'documents' => 'array',
        'active' => 'boolean',
    ];

    // Helper method to get all document types
    public function getDocumentTypes(): array
    {
        return [
            'dob_image' => 'Date of Birth Certificate',
            'personal_image' => 'Personal Photo',
            'practice_exam_result_image' => 'Practice Exam Result',
            'practice_license_form_image' => 'Practice License Form',
            'graduation_certificate_image' => 'Graduation Certificate',
            'internship_certificate_image' => 'Internship Certificate',
            'military_service_status_image' => 'Military Service Status',
            'criminal_record_certificate_image' => 'Criminal Record Certificate',
            'syndicate_registration_form_image' => 'Syndicate Registration Form',
        ];
    }
}
