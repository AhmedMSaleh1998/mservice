<?php

namespace App\Filament\Resources\ServiceTypes\Pages;

use App\Filament\Resources\ServiceTypes\ServiceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceTypes extends ListRecords
{
    protected static string $resource = ServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
