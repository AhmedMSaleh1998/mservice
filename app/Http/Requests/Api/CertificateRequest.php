<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'certificate_id' => ['required', 'exists:certificates,id'],
            'delivery_method' => ['required', Rule::in(['digital', 'delivery'])],
            'phone' => [
                'nullable',
                'exclude_unless:delivery_method,digital',
                'required_without:email',
            ],
            'email' => [
                'nullable',
                'email',
                'exclude_unless:delivery_method,digital',
                'required_without:phone',
            ],
            'address_id' => [
                'required_if:delivery_method,delivery',
                'nullable',
                'exists:user_addresses,id'
            ],

        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Please provide either a phone number or an email address for digital delivery.',
            'email.required_without' => 'Please provide either an email address or a phone number for digital delivery.',
            'user_address_id.required_if' => 'Please select a delivery address.',
        ];
    }
}
