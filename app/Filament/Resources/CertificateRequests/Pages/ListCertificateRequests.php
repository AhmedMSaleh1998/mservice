<?php

namespace App\Filament\Resources\CertificateRequests\Pages;

use App\Filament\Resources\CertificateRequests\CertificateRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListCertificateRequests extends ListRecords
{
    protected static string $resource = CertificateRequestResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
