<?php

namespace App\Filament\Resources\AdminNotifications\Pages;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use App\Filament\Resources\AdminNotifications\Schemas\AdminNotificationForm;
use Filament\Resources\Pages\CreateRecord;
use Modules\Notifications\Models\AdminNotification;

class CreateAdminNotification extends CreateRecord
{
    protected static string $resource = AdminNotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = AdminNotificationForm::normalizeRecipients($data);
        $data['status'] = AdminNotification::STATUS_DRAFT;
        $data['created_by'] = auth('admin')->id();

        return $data;
    }

    // Saving only creates a draft; the admin reviews it and sends it from the
    // view page, after confirming how many users it reaches.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
