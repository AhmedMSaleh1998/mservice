<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePhoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
            'old_phone' => ['required', 'string', 'min:8'],
            'new_phone' => ['required', 'string', 'min:8', Rule::unique('users', 'phone')->ignore($this->user()->id)],
        ];
    }


    public function messages(): array
    {
        return [
            'password.current_password' => __('Password is incorrect.'),
            'new_phone.unique' => __('This phone number is already in use.'),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->old_phone !== $this->user()->phone) {
                $validator->errors()->add(
                    'old_phone',
                    __('Old phone number does not match your current phone.')
                );
            }
        });
    }
}
