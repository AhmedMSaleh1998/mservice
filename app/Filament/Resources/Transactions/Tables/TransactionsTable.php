<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\OrderAdminSupport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Order;
use Modules\Core\Services\OrderService;

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
                TextColumn::make('oracle_sync')
                    ->label(__('Oracle Sync'))
                    ->badge()
                    ->getStateUsing(fn (Order $record): string => (string) (data_get($record->payload, 'oracle_payment_sync.status') ?: 'none'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => __('Synced'),
                        'failed' => __('Sync failed'),
                        'skipped' => __('Sync skipped'),
                        default => __('Not synced'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'warning',
                    })
                    ->tooltip(fn (Order $record): ?string => data_get($record->payload, 'oracle_payment_sync.message')),
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
                // The sync outcome lives inside the payload JSON rather than in a
                // column of its own, so the filter has to reach into it directly.
                SelectFilter::make('oracle_sync')
                    ->label(__('Oracle Sync'))
                    ->options([
                        'success' => __('Synced'),
                        'failed' => __('Sync failed'),
                        'skipped' => __('Sync skipped'),
                        'none' => __('Not synced'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        // "none" is every order the sync never wrote a status for,
                        // which is exactly what the column renders as not synced.
                        if ($value === 'none') {
                            return $query->where(fn (Builder $query): Builder => $query
                                ->whereNull('payload->oracle_payment_sync->status')
                                ->orWhere('payload->oracle_payment_sync->status', ''));
                        }

                        return $query->where('payload->oracle_payment_sync->status', $value);
                    }),
            ])
            ->recordUrl(fn (Order $record): string => TransactionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('syncOracle')
                        ->label(__('Sync with Oracle'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        // Only for paid orders that are not already synced successfully.
                        ->visible(fn (Order $record): bool => $record->status === 'paid_successfully'
                            && ! app(OrderService::class)->hasSuccessfulOraclePaymentSync($record))
                        ->requiresConfirmation()
                        ->modalHeading(__('Sync with Oracle'))
                        ->action(function (Order $record): void {
                            $result = app(OrderService::class)->runOraclePaymentSync($record);

                            if (($result['status'] ?? null) === 'success') {
                                Notification::make()
                                    ->title(__('Payment synced with Oracle successfully.'))
                                    ->success()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('Oracle sync did not succeed.'))
                                ->body($result['message'] ?? null)
                                ->danger()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
