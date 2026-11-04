<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Modules\Services\Models\Service;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * The app and the code find services by `key`, so it is not typed by hand:
     * it is derived once from the English title and never changes afterwards.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug((string) data_get($data, 'title.en')) ?: 'service';
        $key = $base;

        for ($suffix = 2; Service::query()->withTrashed()->where('key', $key)->exists(); $suffix++) {
            $key = "{$base}-{$suffix}";
        }

        $data['key'] = $key;

        return $data;
    }
}
