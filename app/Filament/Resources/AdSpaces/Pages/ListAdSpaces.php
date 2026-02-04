<?php

namespace App\Filament\Resources\AdSpaces\Pages;

use App\Filament\Resources\AdSpaces\AdSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAdSpaces extends ListRecords
{
    protected static string $resource = AdSpaceResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
