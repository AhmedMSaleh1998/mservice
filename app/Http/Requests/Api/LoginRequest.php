<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Services\DeviceTokenService;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:7'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string'],
            'fcm_token' => ['sometimes', 'nullable', 'string', 'max:512'],
            'platform' => ['sometimes', 'nullable', 'string', Rule::in(DeviceTokenService::PLATFORMS)],
        ];
    }
}
