<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTravelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.travel_category_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
