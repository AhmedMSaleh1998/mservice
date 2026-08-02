<?php

namespace App\Filament\Resources\CertificateRequests\Pages;

use App\Filament\Resources\CertificateRequests\CertificateRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditCertificateRequest extends EditRecord
{
    protected static string $resource = CertificateRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
