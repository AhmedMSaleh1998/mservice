<?php

namespace App\Filament\Resources\MedicalGuides\Pages;

use App\Filament\Resources\MedicalGuides\MedicalGuideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicalGuide extends CreateRecord
{
    protected static string $resource = MedicalGuideResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
