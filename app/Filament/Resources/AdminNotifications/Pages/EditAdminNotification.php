<?php

namespace App\Filament\Resources\AdminNotifications\Pages;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use App\Filament\Resources\AdminNotifications\Schemas\AdminNotificationForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminNotification extends EditRecord
{
    protected static string $resource = AdminNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AdminNotificationForm::normalizeRecipients($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
