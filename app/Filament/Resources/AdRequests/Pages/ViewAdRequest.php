<?php

namespace App\Filament\Resources\AdRequests\Pages;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAdRequest extends ViewRecord
{
    protected static string $resource = AdRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdRequestResource::approveAction(),
            AdRequestResource::rejectAction(),
            AdRequestResource::toggleVisibilityAction(),
            EditAction::make(),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Model $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing(['user', 'adSpace.service', 'order']);
    }
}
