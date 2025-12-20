<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'neqaba_address' => 'nullable|string',
            'national_id' => ['nullable', 'string', Rule::unique('users', 'national_id')->ignore($this->user()->id)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => ['nullable', 'string', 'email', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }
}
