<?php

namespace App\Filament\Resources\Travels\Pages;

use App\Filament\Resources\Travels\TravelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class ViewTravel extends ViewRecord
{
    protected static string $resource = TravelResource::class;

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

        return $record->loadMissing('province');
    }
}
