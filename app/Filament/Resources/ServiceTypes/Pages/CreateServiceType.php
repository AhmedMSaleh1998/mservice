<?php

namespace App\Filament\Resources\ServiceTypes\Pages;

use App\Filament\Resources\ServiceTypes\ServiceTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceType extends CreateRecord
{
    protected static string $resource = ServiceTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
