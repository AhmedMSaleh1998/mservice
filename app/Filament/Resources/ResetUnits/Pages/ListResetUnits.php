<?php

namespace App\Filament\Resources\ResetUnits\Pages;

use App\Filament\Resources\ResetUnits\ResetUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResetUnits extends ListRecords
{
    protected static string $resource = ResetUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
