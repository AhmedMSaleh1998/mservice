<?php

namespace App\Filament\Resources\AdSpaces\Pages;

use App\Filament\Resources\AdSpaces\AdSpaceResource;
use Filament\Resources\Pages\EditRecord;

class EditAdSpace extends EditRecord
{
    protected static string $resource = AdSpaceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
