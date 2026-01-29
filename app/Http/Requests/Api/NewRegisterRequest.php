<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\GenderEnum;
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
            'national_id' => ['required','string','unique:users','min:14',],
            'issued_from' => ['required', 'string', 'max:100'],
            'governorate' => ['required', 'integer', 'exists:provinces,id'],
            'birth_date' => ['required', 'date'],
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
}
