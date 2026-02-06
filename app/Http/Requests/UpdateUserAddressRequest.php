<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'province_id' => ['sometimes', 'exists:provinces,id'],
            'district' => ['sometimes', 'string', 'max:255'],
            'street' => ['sometimes', 'string', 'max:255'],
            'lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'min:10' , 'max:10'],
            'address_name' => ['sometimes', 'string', 'max:255'],
            'unit_number' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
