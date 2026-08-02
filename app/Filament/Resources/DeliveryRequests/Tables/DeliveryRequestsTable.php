<?php

namespace App\Filament\Resources\DeliveryRequests\Tables;

use App\Filament\Resources\DeliveryRequests\DeliveryRequestResource;
use App\Support\OrderAdminSupport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Order;

class DeliveryRequestsTable
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
                TextColumn::make('delivery_status')
                    ->label(__('Delivery Status'))
                    ->getStateUsing(fn (Order $record): string => OrderAdminSupport::deliveryStatusLabel(OrderAdminSupport::deliveryStatus($record)))
                    ->badge()
                    ->color(fn (Order $record): string => OrderAdminSupport::deliveryStatusColor(OrderAdminSupport::deliveryStatus($record))),
                TextColumn::make('service_status')
                    ->label(__('Service Status'))
                    ->getStateUsing(fn (Order $record): string => OrderAdminSupport::serviceStatusLabel($record))
                    ->badge()
                    ->color(fn (Order $record): string => OrderAdminSupport::orderStatusColor(OrderAdminSupport::serviceStatus($record))),
                TextColumn::make('status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OrderAdminSupport::statusLabel($state))
                    ->color(fn (?string $state): string => OrderAdminSupport::orderStatusColor($state)),
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
                    ->options(OrderAdminSupport::deliveryTypeOptions()),
                SelectFilter::make('delivery_status')
                    ->label(__('Delivery Status'))
                    ->options(OrderAdminSupport::deliveryStatusOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return $query->whereHasMorph(
                            'orderable',
                            array_keys(OrderAdminSupport::deliveryTypeOptions()),
                            fn (Builder $orderableQuery) => $orderableQuery->where('delivery_status', $value)
                        );
                    }),
                SelectFilter::make('status')
                    ->label(__('Payment Status'))
                    ->options(OrderAdminSupport::orderStatusOptions()),
            ])
            ->recordUrl(fn (Order $record): string => DeliveryRequestResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    static::updateDeliveryStatusAction(),
                    ViewAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::updateSelectedDeliveryStatusesBulkAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function updateDeliveryStatusAction(): Action
    {
        return Action::make('updateDeliveryStatus')
            ->label(__('Update Delivery Status'))
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->authorize(fn (): bool => auth()->user()?->can('Update:Order') ?? false)
            ->fillForm(fn (Order $record): array => [
                'delivery_status' => OrderAdminSupport::deliveryStatus($record),
            ])
            ->schema([
                Select::make('delivery_status')
                    ->label(__('Delivery Status'))
                    ->options(OrderAdminSupport::deliveryStatusOptions())
                    ->required(),
            ])
            ->action(function (Order $record, array $data): void {
                $orderable = $record->orderable;

                if (! $orderable || ! OrderAdminSupport::hasPhysicalDelivery($record)) {
                    return;
                }

                $orderable->update([
                    'delivery_status' => $data['delivery_status'],
                ]);

                OrderAdminSupport::loadRelations($record);

                Notification::make()
                    ->title(__('Delivery status updated successfully.'))
                    ->success()
                    ->send();
            })
            ->visible(fn (Order $record): bool => OrderAdminSupport::hasPhysicalDelivery($record));
    }

    public static function updateSelectedDeliveryStatusesBulkAction(): BulkAction
    {
        return BulkAction::make('updateSelectedDeliveryStatuses')
            ->label(__('Update Delivery Status'))
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->authorize(fn (): bool => auth()->user()?->can('Update:Order') ?? false)
            ->schema([
                Select::make('delivery_status')
                    ->label(__('Delivery Status'))
                    ->options(OrderAdminSupport::deliveryStatusOptions())
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $updated = 0;

                $records->each(function (Order $record) use ($data, &$updated): void {
                    $orderable = $record->orderable;

                    if (! $orderable || ! OrderAdminSupport::hasPhysicalDelivery($record)) {
                        return;
                    }

                    $orderable->update([
                        'delivery_status' => $data['delivery_status'],
                    ]);

                    $updated++;
                });

                if ($updated === 0) {
                    Notification::make()
                        ->title(__('No delivery requests were selected.'))
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Delivery status updated for :count requests.', ['count' => $updated]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
