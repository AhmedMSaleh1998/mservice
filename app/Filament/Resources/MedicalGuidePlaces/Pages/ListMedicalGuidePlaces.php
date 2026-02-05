<?php

namespace App\Filament\Resources\MedicalGuidePlaces\Pages;

use App\Filament\Resources\MedicalGuidePlaces\MedicalGuidePlaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMedicalGuidePlaces extends ListRecords
{
    protected static string $resource = MedicalGuidePlaceResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
