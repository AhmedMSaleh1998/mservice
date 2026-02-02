<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationRequest extends CreateRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['active'] = false;
        $data['reg_code'] = $data['reg_code'] ?? ('EMS' . random_int(11111, 99999));

        return $data;
    }
}
