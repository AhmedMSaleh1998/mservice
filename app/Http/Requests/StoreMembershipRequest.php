<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'print_card' => $this->boolean('print_card'),
        ]);
    }

    public function rules(): array
    {
        return [
            'print_card' => ['sometimes', 'boolean'],
            'delivery_method' => ['nullable', 'in:delivery,digital'],
            'address_id' => [
                'nullable',
                // Only a physical delivery needs an address; a digital card does not.
                'required_if:delivery_method,delivery',
                Rule::exists('user_addresses', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'address_id.required_if' => __('Please select a delivery address.'),
            'address_id.exists' => __('The selected address is invalid.'),
        ];
    }
}
