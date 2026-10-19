<?php

namespace App\Filament\Resources\AdRequests\Tables;

use App\Filament\Resources\AdRequests\AdRequestResource;
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
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'adSpace.service', 'order']))
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
                    ->getStateUsing(fn (AdRequest $record): string => AdRequestResource::getAdSpaceLabel($record->adSpace)),
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
                    ->formatStateUsing(fn (?string $state): string => AdRequestResource::statusLabel($state))
                    ->color(fn ($state) => match ($state) {
                        'pending_payment' => 'warning',
                        'payment_expired' => 'gray',
                        'pending_review' => 'info',
                        'paid_successfully' => 'info',
                        'completed' => 'success',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('order.payment_method')
                    ->label(__('Payment Method'))
                    ->formatStateUsing(fn (?string $state): ?string => AdRequestResource::paymentMethodLabel($state)),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('Ends At'))
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
                    AdRequestResource::approveAction(),
                    AdRequestResource::rejectAction(),
                ]),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('created_at', 'desc');
    }
}
