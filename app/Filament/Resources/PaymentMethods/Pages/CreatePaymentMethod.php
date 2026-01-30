<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = $this->makeKeyFromName($data['name'] ?? null);

        return $data;
    }

    private function makeKeyFromName(array|string|null $name): string
    {
        $name = is_array($name) ? $name : ['en' => $name];
        $englishName = $name['en'] ?? null;
        $fallbackName = $englishName;

        if (empty($fallbackName) && is_array($name)) {
            foreach ($name as $value) {
                if (!empty($value)) {
                    $fallbackName = $value;
                    break;
                }
            }
        }

        return Str::slug((string) $fallbackName);
    }
}
