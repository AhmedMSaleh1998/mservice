<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\OrderAdminSupport;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Models\Order;

class TransactionsTable
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
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('orderable_type')
                    ->label(__('Service Type'))
                    ->getStateUsing(fn (Order $record): string => OrderAdminSupport::typeLabel($record))
                    ->badge()
                    ->color('info'),
                TextColumn::make('service_title')
                    ->label(__('Service Details'))
                    ->getStateUsing(fn (Order $record): string => OrderAdminSupport::serviceTitle($record))
                    ->description(fn (Order $record): string => OrderAdminSupport::serviceSummary($record))
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OrderAdminSupport::statusLabel($state))
                    ->color(fn (?string $state): string => OrderAdminSupport::orderStatusColor($state))
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label(__('Payment Method'))
                    ->getStateUsing(fn (Order $record): ?string => OrderAdminSupport::paymentMethodLabel($record->payment_method))
                    ->placeholder('-'),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (mixed $state, Order $record): string => OrderAdminSupport::money($state, $record->currency))
                    ->sortable(),
                TextColumn::make('merchant_ref_num')
                    ->label(__('Merchant Reference'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gateway_reference')
                    ->label(__('Gateway Reference'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label(__('Paid At'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('orderable_type')
                    ->label(__('Service Type'))
                    ->options(OrderAdminSupport::typeOptions()),
                SelectFilter::make('status')
                    ->label(__('Payment Status'))
                    ->options(OrderAdminSupport::orderStatusOptions()),
                SelectFilter::make('payment_method')
                    ->label(__('Payment Method'))
                    ->options(OrderAdminSupport::paymentMethodOptions()),
            ])
            ->recordUrl(fn (Order $record): string => TransactionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
