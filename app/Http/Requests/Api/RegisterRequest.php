<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;


class RegisterRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:5'],
            'email' => ['required', 'email', 'max:255', 'min:5', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:7', Rule::unique('users')->where(function ($query) {
                return $query->where('active', true);
            })],
            'password' => ['required', 'confirmed', Password::min(8)],
            'national_id' => ['required', 'numeric', Rule::unique('users')->where(function ($query) {
                return $query->where('active', true);
            })],
            // Unique across every row, not just the active ones: an abandoned
            // unverified account used to leave the registration number free,
            // which is how one doctor ended up holding several accounts.
            'reg_number' => ['required', 'numeric', Rule::unique('users')],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('This email address is already registered.'),
            'reg_number.unique' => __('This registration number already has an account.'),
        ];
    }
}
