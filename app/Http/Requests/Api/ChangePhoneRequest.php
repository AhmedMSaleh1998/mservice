<?php

namespace App\Http\Requests\Api;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class ChangePhoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
            'old_phone' => ['required', 'string', 'min:8'],
            'new_phone' => ['required', 'string', 'min:8'],
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
            $oldPhoneVariants = PhoneNumberNormalizer::variants((string) $this->old_phone);

            if (! in_array($this->user()->phone, $oldPhoneVariants, true)) {
                $validator->errors()->add(
                    'old_phone',
                    __('Old phone number does not match your current phone.')
                );
            }

            $newPhoneVariants = PhoneNumberNormalizer::variants((string) $this->new_phone);
            $newPhoneExists = $this->user()
                ->newQuery()
                ->whereKeyNot($this->user()->id)
                ->whereIn('phone', $newPhoneVariants)
                ->exists();

            if ($newPhoneExists) {
                $validator->errors()->add(
                    'new_phone',
                    __('This phone number is already in use.')
                );
            }
        });
    }
}
