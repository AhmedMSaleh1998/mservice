<?php

namespace App\Filament\Resources\AdRequests\Pages;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAdRequests extends ListRecords
{
    protected static string $resource = AdRequestResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;
}
