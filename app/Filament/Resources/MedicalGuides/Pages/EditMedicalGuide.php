<?php

namespace App\Filament\Resources\MedicalGuides\Pages;

use App\Filament\Resources\MedicalGuides\MedicalGuideResource;
use Filament\Resources\Pages\EditRecord;

class EditMedicalGuide extends EditRecord
{
    protected static string $resource = MedicalGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
            \Filament\Actions\ForceDeleteAction::make(),
            \Filament\Actions\RestoreAction::make(),
        ];
    }
}
