<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CertificateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'certificate_id' => [
                'required',
                Rule::exists('certificates', 'id')->where(function ($query) {
                    $query->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ],
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
                Rule::exists('user_addresses', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],

        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Please provide either a phone number or an email address for digital delivery.',
            'email.required_without' => 'Please provide either an email address or a phone number for digital delivery.',
            'address_id.required_if' => 'Please select a delivery address.',
            'address_id.exists' => 'The selected address is invalid.',
            'certificate_id.exists' => 'The selected certificate is invalid.',
        ];
    }
}
