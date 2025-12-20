<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationRequest extends CreateRecord
{
    protected static string $resource = RegistrationRequestResource::class;
}
