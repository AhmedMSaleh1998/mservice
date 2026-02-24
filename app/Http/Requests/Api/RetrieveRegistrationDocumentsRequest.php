<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RetrieveRegistrationDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'national_id' => ['required', 'string', 'max:50'],
            'residence_mobile_1_country_code' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'residence_mobile_1' => ['required', 'string', 'regex:/^\d{1,10}$/', 'max:10'],
        ];
    }

    public function attributes(): array
    {
        return [
            'national_id' => __('National ID'),
            'residence_mobile_1_country_code' => __('Mobile 1 Country Code'),
            'residence_mobile_1' => __('Mobile 1 Number'),
        ];
    }
}
