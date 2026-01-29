<?php

namespace App\Filament\Resources\MedicalUniversities\Pages;

use App\Filament\Resources\MedicalUniversities\MedicalUniversityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicalUniversity extends EditRecord
{
    protected static string $resource = MedicalUniversityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
