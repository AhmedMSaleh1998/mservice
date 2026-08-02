<?php

namespace App\Filament\Resources\SmsMessages\Pages;

use App\Filament\Resources\SmsMessages\SmsMessageResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListSmsMessages extends ListRecords
{
    protected static string $resource = SmsMessageResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
