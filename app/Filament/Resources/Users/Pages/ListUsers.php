<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
