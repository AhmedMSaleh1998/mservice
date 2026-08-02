<?php

namespace App\Filament\Resources\Travels\Pages;

use App\Filament\Resources\Travels\TravelResource;
use Filament\Resources\Pages\EditRecord;

class EditTravel extends EditRecord
{
    protected static string $resource = TravelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
            \Filament\Actions\ForceDeleteAction::make(),
            \Filament\Actions\RestoreAction::make(),
        ];
    }
}
