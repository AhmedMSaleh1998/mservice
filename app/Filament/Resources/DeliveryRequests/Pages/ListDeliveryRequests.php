<?php

namespace App\Filament\Resources\DeliveryRequests\Pages;

use App\Filament\Resources\DeliveryRequests\DeliveryRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListDeliveryRequests extends ListRecords
{
    protected static string $resource = DeliveryRequestResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
