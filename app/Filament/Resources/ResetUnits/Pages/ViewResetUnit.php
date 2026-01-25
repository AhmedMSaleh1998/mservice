<?php

namespace App\Filament\Resources\ResetUnits\Pages;

use App\Filament\Resources\ResetUnits\ResetUnitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewResetUnit extends ViewRecord
{
    protected static string $resource = ResetUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
