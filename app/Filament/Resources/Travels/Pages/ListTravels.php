<?php

namespace App\Filament\Resources\Travels\Pages;

use App\Filament\Resources\Travels\TravelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListTravels extends ListRecords
{
    protected static string $resource = TravelResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
