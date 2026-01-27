<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewRegisterRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^([0-9\s\-\+\(\)]*)$/',
                'min:11',
                'unique:registration_requests'
            ],
            'national_id' => [
                'required',
                'string',
                'unique:users'
            ],

            'personal_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'dob_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'graduation_certificate_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'internship_certificate_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'syndicate_registration_form_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'practice_license_form_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'practice_exam_result_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'criminal_record_certificate_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'military_service_status_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered with an active account.',
            'national_id.unique' => 'This national ID is already registered.',
        ];
    }
}
