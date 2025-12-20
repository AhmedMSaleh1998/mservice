<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistrationRequest extends ViewRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
