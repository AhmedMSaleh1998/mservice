<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'province_id' => ['required', 'exists:provinces,id'],
            'district' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required ', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_name' => ['required', 'string', 'max:255'],
            'unit_number' => ['required', 'string', 'max:255'],
        ];
    }
}
