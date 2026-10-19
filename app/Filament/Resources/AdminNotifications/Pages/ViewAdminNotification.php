<?php

namespace App\Filament\Resources\AdminNotifications\Pages;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Notifications\Services\AdminNotificationService;

class ViewAdminNotification extends ViewRecord
{
    protected static string $resource = AdminNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(__('Send now'))
                ->icon(Heroicon::PaperAirplane)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Send notification'))
                ->modalDescription(fn (): string => __('This notification will be sent to :count users via :channels. It cannot be undone.', [
                    'count' => number_format(app(AdminNotificationService::class)->countRecipients($this->record)),
                    'channels' => collect($this->record->channels)
                        ->map(fn (string $channel): string => AdminNotificationResource::channelLabel($channel))
                        ->implode(' + '),
                ]))
                ->modalSubmitActionLabel(__('Send now'))
                ->action(function (AdminNotificationService $adminNotificationService): void {
                    if ($adminNotificationService->countRecipients($this->record) === 0) {
                        Notification::make()
                            ->title(__('No users match these recipients.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $adminNotificationService->send($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('The notification is being sent. The results update on this page.'))
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->isDraft()),
            EditAction::make()
                ->visible(fn (): bool => $this->record->isDraft()),
        ];
    }
}
