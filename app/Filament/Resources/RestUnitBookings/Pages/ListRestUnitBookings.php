<?php

namespace App\Filament\Resources\RestUnitBookings\Pages;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListRestUnitBookings extends ListRecords
{
    protected static string $resource = RestUnitBookingResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
