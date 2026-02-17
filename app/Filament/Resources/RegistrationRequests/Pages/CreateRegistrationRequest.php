<?php

namespace App\Filament\Resources\RegistrationRequests\Pages;

use App\Filament\Resources\RegistrationRequests\RegistrationRequestResource;
use App\Models\RegistrationRequest;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationRequest extends CreateRecord
{
    protected static string $resource = RegistrationRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['active'] = false;
        $data['status'] = RegistrationRequest::STATUS_PENDING_REVIEW;
        $data['reg_code'] = $data['reg_code'] ?? ('EMS' . random_int(11111, 99999));
        $data['license_image'] = $data['license_image'] ?? data_get($data, 'documents.license_image');

        return $data;
    }
}
