<?php

namespace App\Filament\Resources\RestUnitBookings\Pages;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRestUnitBooking extends EditRecord
{
    protected static string $resource = RestUnitBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
