<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;

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
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $adSpace = is_numeric($value)
                        ? AdSpace::query()->find((int) $value)
                        : null;

                    if (! $adSpace) {
                        $fail(__('The selected ad space id is invalid.'));

                        return;
                    }

                    if ($adSpace->is_available || $this->userOwnsActiveReservation((int) $adSpace->id)) {
                        return;
                    }

                    $fail(__('The selected ad space id is invalid.'));
                },
            ],
            'duration_months' => ['required', 'integer', 'min:1'],
            'ad_text' => ['nullable', 'string'],
            'design_image' => [
                Rule::requiredIf(fn (): bool => ! $this->hasEditableReservation()),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
            'payment_method' => [
                'nullable',
                Rule::exists('payment_methods', 'key')->where('is_active', true),
            ],
        ];
    }

    private function hasEditableReservation(): bool
    {
        $adSpaceId = (int) $this->input('ad_space_id');
        $userId = $this->user()?->id;

        if ($userId === null || $adSpaceId <= 0) {
            return false;
        }

        return AdRequest::query()
            ->where('user_id', $userId)
            ->where('ad_space_id', $adSpaceId)
            ->whereIn('status', AdRequest::EDITABLE_PRE_PAYMENT_STATUSES)
            ->exists();
    }

    private function userOwnsActiveReservation(int $adSpaceId): bool
    {
        $userId = $this->user()?->id;

        if ($userId === null || $adSpaceId <= 0) {
            return false;
        }

        return AdRequest::query()
            ->where('user_id', $userId)
            ->where('ad_space_id', $adSpaceId)
            ->whereIn('status', AdRequest::ACTIVE_RESERVATION_STATUSES)
            ->exists();
    }
}
