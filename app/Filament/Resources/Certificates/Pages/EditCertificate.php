<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use Filament\Resources\Pages\EditRecord;

class EditCertificate extends EditRecord
{
    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
