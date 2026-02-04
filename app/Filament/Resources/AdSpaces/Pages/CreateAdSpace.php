<?php

namespace App\Filament\Resources\AdSpaces\Pages;

use App\Filament\Resources\AdSpaces\AdSpaceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdSpace extends CreateRecord
{
    protected static string $resource = AdSpaceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
