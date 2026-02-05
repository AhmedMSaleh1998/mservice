<?php

namespace App\Filament\Resources\MedicalGuides\Pages;

use App\Filament\Resources\MedicalGuides\MedicalGuideResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMedicalGuide extends ViewRecord
{
    protected static string $resource = MedicalGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
        ];
    }
}
