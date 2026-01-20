<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'specialty' => 'required|string|max:255',
            'degree' => 'required|string|max:255',
            'registration_number' => 'required|string|max:50',
            'delivery_method' => 'required|in:delivery,pickup',
            'payment_method' => 'required|in:fawry,instapay',
            'address_id' => 'required|exists:user_addresses,id',
        ];
    }
}
