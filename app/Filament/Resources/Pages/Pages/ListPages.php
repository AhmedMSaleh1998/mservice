<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
