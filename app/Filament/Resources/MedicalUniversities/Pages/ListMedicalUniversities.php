<?php

namespace App\Filament\Resources\MedicalUniversities\Pages;

use App\Filament\Resources\MedicalUniversities\MedicalUniversityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMedicalUniversities extends ListRecords
{
    protected static string $resource = MedicalUniversityResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
