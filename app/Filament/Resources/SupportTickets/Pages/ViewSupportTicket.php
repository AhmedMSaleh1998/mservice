<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markSolved')
                ->label(__('Mark as Solved'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'solved']);

                    Notification::make()
                        ->title(__('Solved'))
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status !== 'solved'),
        ];
    }
}
