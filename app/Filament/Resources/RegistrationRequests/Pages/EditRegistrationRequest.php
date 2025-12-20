<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationRequest extends EditRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
