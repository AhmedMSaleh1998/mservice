<?php

namespace App\Filament\Resources\CertificateRequests\Pages;

use App\Filament\Resources\CertificateRequests\CertificateRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class ViewCertificateRequest extends ViewRecord
{
    protected static string $resource = CertificateRequestResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Model $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing(['user', 'certificate', 'userAddress.province', 'order']);
    }
}
