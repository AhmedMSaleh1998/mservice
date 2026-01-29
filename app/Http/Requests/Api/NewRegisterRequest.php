<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class NewRegisterRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            //personal infromations
            'full_name_ar' => ['required', 'string', 'max:255'],
            'full_name_en' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', Rule::in(['male', 'female'])],
            'nationality' => ['required', 'string', 'max:100'],
            'religion' => ['required', 'string', 'max:100'],
            'national_id' => ['required','string','unique:users','min:14',],
            'issued_from' => ['required', 'string', 'max:100'],
            'governorate' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'birth_governorate' => ['required', 'string', 'max:100'],

            //residence address
            'residence_house_number' => ['required', 'string', 'max:10'],
            'residence_street' => ['required', 'string', 'max:255'],
            'residence_center' => ['required', 'string', 'max:100'],
            'residence_governorate' => ['required', 'string', 'max:100'],
            'residence_phone' => ['required','string','regex:/^([0-9\s\-\+\(\)]*)$/','max:25'],
            'residence_mobile_1' => ['required','string','regex:/^([0-9\s\-\+\(\)]*)$/','min:11','unique:registration_requests'],
            'residence_mobile_2' => ['required','string','regex:/^([0-9\s\-\+\(\)]*)$/','min:11','unique:registration_requests'],
            'email' => ['required', 'email', 'max:255'],

            //university data
            'faculty' => ['required', 'string', 'max:255'],
            'graduation_month' => ['required', 'string', 'max:2'],
            'graduation_year' => ['required', 'string', 'max:10'],
            'university' => ['required', 'string', 'max:255'],
            'grade' => ['required', 'string', 'max:100'],
            'first_foreign_language' => ['required', 'string', 'max:50'],
            'second_foreign_language' => ['sometimes', 'string', 'max:50'],

            //documents
            'personal_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'national_id_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'graduation_certificate_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'internship_certificate_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'criminal_record_certificate_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            'dob_image' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
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
