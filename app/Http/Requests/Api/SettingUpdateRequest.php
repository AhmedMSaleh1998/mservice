<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if ($this->has('lang')) {
            $this->merge([
                'lang' => strtolower($this->lang),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'lang' => 'nullable|string|in:ar,en',
            'notification_enabled' => 'nullable|boolean',
        ];
    }


    public function messages(): array
    {
        return [
            'lang.string' => __('Language must be a valid string.'),
            'lang.in' => __('Language must be either Arabic (ar) or English (en).'),
            'notification_enabled.boolean' => __('Notification enabled must be true or false.'),
        ];
    }
}
