<?php

namespace App\Filament\Resources\Religions\Pages;

use App\Filament\Resources\Religions\ReligionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReligion extends EditRecord
{
    protected static string $resource = ReligionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
