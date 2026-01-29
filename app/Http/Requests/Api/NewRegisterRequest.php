<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\GenderEnum;
use Carbon\Carbon;
class NewRegisterRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            //personal infromations
            'full_name_ar' => ['required', 'string', 'max:255'],
            'full_name_en' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', Rule::in(GenderEnum::values())],
            'nationality' => ['required', 'integer', 'exists:nationalities,id'],
            'religion' => ['required', 'integer', 'exists:religions,id'],
            'national_id' => ['required','string','unique:users','min:14','max:14'],
            'issued_from' => ['required', 'string', 'max:100'],
            'governorate' => ['required', 'integer', 'exists:provinces,id'],
            'birth_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    if (Carbon::parse($value)->age < 23) {
                        $fail(__('Minimum age for graduates is 23 years.'));
                    }
                },
            ],
            'birth_governorate' => ['required', 'integer', 'exists:provinces,id'],

            //residence address
            'residence_house_number' => ['required', 'string', 'max:10'],
            'residence_street' => ['required', 'string', 'max:255'],
            'residence_center' => ['required', 'string', 'max:100'],
            'residence_governorate' => ['required', 'integer', 'exists:provinces,id'],
            'residence_phone' => ['required','string','regex:/^([0-9\s\-\+\(\)]*)$/','max:10'],
            'residence_mobile_1' => ['required','string','regex:/^([0-9\s\-\+\(\)]*)$/','min:11','unique:registration_requests'],
            'residence_mobile_2' => ['sometimes','string','regex:/^([0-9\s\-\+\(\)]*)$/','min:11','unique:registration_requests'],
            'email' => ['required', 'email', 'max:255'],

            //university data
            'faculty' => ['required', 'string', 'max:255'],
            'graduation_month' => ['required', 'string', 'max:2'],
            'graduation_year' => ['required', 'string', 'max:10'],
            'university' => ['required', 'integer', 'exists:medical_universities,id'],
            'grade' => ['required', 'integer', 'exists:grades,id'],
            'first_foreign_language' => ['required', 'integer', 'exists:languages,id'],
            'second_foreign_language' => ['nullable', 'integer', 'exists:languages,id'],

            //documents
            'personal_image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'national_id_image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'graduation_certificate_image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'internship_certificate_image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'criminal_record_certificate_image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
            'dob_image' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'This phone number is already registered with an active account.',
            'national_id.unique' => 'This national ID is already registered.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name_ar' => __('Full Name (AR)'),
            'full_name_en' => __('Full Name (EN)'),
            'gender' => __('Gender'),
            'nationality' => __('Nationality'),
            'religion' => __('Religion'),
            'national_id' => __('National ID'),
            'issued_from' => __('Issued From'),
            'governorate' => __('Governorate'),
            'birth_date' => __('Birth Date'),
            'birth_governorate' => __('Birth Governorate'),
            'residence_house_number' => __('House Number'),
            'residence_street' => __('Street'),
            'residence_center' => __('Center'),
            'residence_governorate' => __('Residence Governorate'),
            'residence_phone' => __('Residence Phone'),
            'residence_mobile_1' => __('Mobile 1'),
            'residence_mobile_2' => __('Mobile 2'),
            'email' => __('Email'),
            'faculty' => __('Faculty'),
            'graduation_month' => __('Graduation Month'),
            'graduation_year' => __('Graduation Year'),
            'university' => __('University'),
            'grade' => __('Grade'),
            'first_foreign_language' => __('First Foreign Language'),
            'second_foreign_language' => __('Second Foreign Language'),
            'personal_image' => __('Personal Photo'),
            'national_id_image' => __('National ID Photo'),
            'graduation_certificate_image' => __('Graduation Certificate'),
            'internship_certificate_image' => __('Internship Certificate'),
            'criminal_record_certificate_image' => __('Criminal Record Certificate'),
            'dob_image' => __('Date of Birth Certificate'),
        ];
    }
}
