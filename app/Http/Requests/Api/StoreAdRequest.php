<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('months') && ! $this->filled('duration_months')) {
            $this->merge(['duration_months' => $this->input('months')]);
        }
    }

    public function rules(): array
    {
        return [
            'ad_space_id' => [
                'required',
                'integer',
                Rule::exists('ad_spaces', 'id')->where('is_available', true),
            ],
            'duration_months' => ['required', 'integer', 'min:1'],
            'ad_text' => ['nullable', 'string'],
            'design_image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'payment_method' => [
                'nullable',
                Rule::exists('payment_methods', 'key')->where('is_active', true),
            ],
        ];
    }
}
