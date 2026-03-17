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
            'email' => ['required', 'email', 'max:255', 'min:5'],
            'phone' => ['required', 'string', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'min:7', Rule::unique('users')->where(function ($query) {
                return $query->where('active', true);
            })],
            'password' => ['required', 'confirmed', Password::min(8)],
            'national_id' => ['required', 'numeric', Rule::unique('users')->where(function ($query) {
                return $query->where('active', true);
            })],
            'reg_number' => ['required', 'numeric', Rule::unique('users')->where(function ($query) {
                return $query->where('active', true);
            })],
        ];
    }
}
