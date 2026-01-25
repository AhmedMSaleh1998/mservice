<?php

namespace App\Filament\Resources\MedicalGuides\Pages;

use App\Filament\Resources\MedicalGuides\MedicalGuideResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListMedicalGuides extends ListRecords
{
    protected static string $resource = MedicalGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
