<?php

namespace App\Filament\Resources\AdRequests\Tables;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Ads\Enums\AdRequestStatusEnum;
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
                    ->formatStateUsing(fn (?string $state): string => AdRequestStatusEnum::labelFor($state))
                    ->color(fn (?string $state): string => AdRequestStatusEnum::colorFor($state))
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
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AdRequestStatusEnum::options())
                    ->multiple()
                    // The list opens on paid requests that never went live
                    // (awaiting approval or rejected); approved ones live under
                    // Ads. Clearing the filter shows every status.
                    ->default(AdRequestStatusEnum::values(AdRequestStatusEnum::requestStatuses())),
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
