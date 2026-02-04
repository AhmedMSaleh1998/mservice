<?php

namespace App\Filament\Resources\AdRequests\Tables;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Ads\Models\AdRequest;

class AdRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adSpace.name')
                    ->label(__('Ad Space'))
                    ->getStateUsing(fn ($record) => $record->adSpace?->getTranslation('name', app()->getLocale())),
                TextColumn::make('duration_months')
                    ->label(__('Months'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('Total Amount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending_payment' => 'warning',
                        'pending_review' => 'info',
                        'paid_successfully' => 'info',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label(__('Payment Method'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ViewAction::make()
                        ->url(fn (AdRequest $record) => AdRequestResource::getUrl('view', ['record' => $record])),
                    Action::make('approve')
                        ->label(__('Approve'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (AdRequest $record) {
                            $record->update(['status' => 'approved']);

                            \Filament\Notifications\Notification::make()
                                ->title(__('Approved'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn (AdRequest $record) => $record->status === 'paid_successfully'),
                    Action::make('reject')
                        ->label(__('Reject'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (AdRequest $record) {
                            $record->update(['status' => 'rejected']);

                            \Filament\Notifications\Notification::make()
                                ->title(__('Rejected'))
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (AdRequest $record) => $record->status === 'paid_successfully'),
                ]),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('created_at', 'desc');
    }
}
