<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
