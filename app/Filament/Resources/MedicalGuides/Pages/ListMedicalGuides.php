<?php

namespace App\Filament\Resources\MedicalGuides\Pages;

use App\Filament\Resources\MedicalGuides\MedicalGuideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListMedicalGuides extends ListRecords
{
    protected static string $resource = MedicalGuideResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
