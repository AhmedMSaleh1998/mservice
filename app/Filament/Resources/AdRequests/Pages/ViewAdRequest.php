<?php

namespace App\Filament\Resources\AdRequests\Pages;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAdRequest extends ViewRecord
{
    protected static string $resource = AdRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'approved']);

                    Notification::make()
                        ->title(__('Approved'))
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status === 'paid_successfully'),
            Action::make('reject')
                ->label(__('Reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'rejected']);

                    Notification::make()
                        ->title(__('Rejected'))
                        ->warning()
                        ->send();
                })
                ->visible(fn () => $this->record->status === 'paid_successfully'),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Model $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing(['user', 'adSpace.service', 'order']);
    }
}
