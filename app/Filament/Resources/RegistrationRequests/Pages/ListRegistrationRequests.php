<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationRequests extends ListRecords
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
