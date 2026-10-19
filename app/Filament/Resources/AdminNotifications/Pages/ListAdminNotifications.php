<?php

namespace App\Filament\Resources\AdminNotifications\Pages;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAdminNotifications extends ListRecords
{
    protected static string $resource = AdminNotificationResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
