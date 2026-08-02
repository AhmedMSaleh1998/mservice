<?php

namespace App\Filament\Resources\Travels\Pages;

use App\Filament\Resources\Travels\TravelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTravel extends CreateRecord
{
    protected static string $resource = TravelResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
