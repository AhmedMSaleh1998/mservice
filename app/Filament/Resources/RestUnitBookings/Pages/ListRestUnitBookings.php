<?php

namespace App\Filament\Resources\RestUnitBookings\Pages;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRestUnitBookings extends ListRecords
{
    protected static string $resource = RestUnitBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
