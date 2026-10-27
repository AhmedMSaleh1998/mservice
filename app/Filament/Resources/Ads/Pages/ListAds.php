<?php

namespace App\Filament\Resources\Ads\Pages;

use App\Filament\Resources\Ads\AdResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAds extends ListRecords
{
    protected static string $resource = AdResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;
}
