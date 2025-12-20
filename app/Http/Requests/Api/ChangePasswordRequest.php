<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.current_password' => __('Old password is incorrect.'),
            'new_password.min' => __('New password must be at least 8 characters.'),
            'new_password.confirmed' => __('New password confirmation does not match.'),
        ];
    }
}
