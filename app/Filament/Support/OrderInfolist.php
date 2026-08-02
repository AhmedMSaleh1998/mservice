<?php

namespace App\Filament\Support;

use App\Support\OrderAdminSupport;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Models\Order;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Transaction Summary'))
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('Transaction ID'))
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('Payment Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => OrderAdminSupport::statusLabel($state))
                            ->color(fn (?string $state): string => OrderAdminSupport::orderStatusColor($state)),
                        TextEntry::make('amount')
                            ->label(__('Amount'))
                            ->formatStateUsing(fn (mixed $state, Order $record): string => OrderAdminSupport::money($state, $record->currency)),
                        TextEntry::make('currency')
                            ->label(__('Currency'))
                            ->placeholder('-'),
                        TextEntry::make('payment_method')
                            ->label(__('Payment Method'))
                            ->getStateUsing(fn (Order $record): ?string => OrderAdminSupport::paymentMethodLabel($record->payment_method))
                            ->placeholder('-'),
                        TextEntry::make('provider')
                            ->label(__('Provider'))
                            ->placeholder('-'),
                        TextEntry::make('merchant_ref_num')
                            ->label(__('Merchant Reference'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('gateway_reference')
                            ->label(__('Gateway Reference'))
                            ->placeholder('-')
                            ->copyable(),
                        TextEntry::make('gateway_status')
                            ->label(__('Gateway Status'))
                            ->placeholder('-'),
                        TextEntry::make('paid_at')
                            ->label(__('Paid At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('payment_started_at')
                            ->label(__('Payment Started At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('payment_last_synced_at')
                            ->label(__('Last Synced At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Service Details'))
                    ->schema([
                        TextEntry::make('service_type')
                            ->label(__('Service Type'))
                            ->getStateUsing(fn (Order $record): string => OrderAdminSupport::typeLabel($record))
                            ->badge()
                            ->color('info'),
                        TextEntry::make('service_title')
                            ->label(__('Service'))
                            ->getStateUsing(fn (Order $record): string => OrderAdminSupport::serviceTitle($record))
                            ->placeholder('-'),
                        TextEntry::make('service_reference')
                            ->label(__('Service Reference'))
                            ->getStateUsing(fn (Order $record): ?string => OrderAdminSupport::serviceReference($record))
                            ->placeholder('-'),
                        TextEntry::make('service_status')
                            ->label(__('Service Status'))
                            ->getStateUsing(fn (Order $record): string => OrderAdminSupport::serviceStatusLabel($record))
                            ->badge()
                            ->color(fn (Order $record): string => OrderAdminSupport::orderStatusColor(OrderAdminSupport::serviceStatus($record))),
                        RepeatableEntry::make('service_detail_items')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Order $record): array => OrderAdminSupport::serviceDetailItems($record))
                            ->table([
                                TableColumn::make(__('Field')),
                                TableColumn::make(__('Value')),
                            ])
                            ->schema([
                                TextEntry::make('label')
                                    ->hiddenLabel(),
                                TextEntry::make('value')
                                    ->hiddenLabel()
                                    ->wrap()
                                    ->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('Delivery Details'))
                    ->visible(fn (Order $record): bool => OrderAdminSupport::hasDeliveryDetails($record))
                    ->schema([
                        TextEntry::make('delivery_method')
                            ->label(__('Delivery Method'))
                            ->getStateUsing(fn (Order $record): string => OrderAdminSupport::deliveryMethodLabel(OrderAdminSupport::deliveryMethod($record))),
                        TextEntry::make('delivery_status')
                            ->label(__('Delivery Status'))
                            ->getStateUsing(fn (Order $record): string => OrderAdminSupport::deliveryStatusLabel(OrderAdminSupport::deliveryStatus($record)))
                            ->badge()
                            ->color(fn (Order $record): string => OrderAdminSupport::deliveryStatusColor(OrderAdminSupport::deliveryStatus($record))),
                        RepeatableEntry::make('delivery_detail_items')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Order $record): array => OrderAdminSupport::deliveryDetailItems($record))
                            ->table([
                                TableColumn::make(__('Field')),
                                TableColumn::make(__('Value')),
                            ])
                            ->schema([
                                TextEntry::make('label')
                                    ->hiddenLabel(),
                                TextEntry::make('value')
                                    ->hiddenLabel()
                                    ->wrap()
                                    ->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Pricing Breakdown'))
                    ->schema([
                        TextEntry::make('pricing_notice')
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No pricing breakdown available.'))
                            ->visible(fn (Order $record): bool => empty(OrderAdminSupport::pricingItems($record)))
                            ->columnSpanFull(),
                        RepeatableEntry::make('pricing_items')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Order $record): array => OrderAdminSupport::pricingItems($record))
                            ->visible(fn (Order $record): bool => ! empty(OrderAdminSupport::pricingItems($record)))
                            ->table([
                                TableColumn::make(__('Name')),
                                TableColumn::make(__('Description')),
                                TableColumn::make(__('Price')),
                                TableColumn::make(__('Count')),
                                TableColumn::make(__('Total Amount')),
                            ])
                            ->schema([
                                TextEntry::make('label')
                                    ->hiddenLabel(),
                                TextEntry::make('description')
                                    ->hiddenLabel()
                                    ->placeholder('-'),
                                TextEntry::make('unit_price')
                                    ->hiddenLabel()
                                    ->money('EGP'),
                                TextEntry::make('quantity')
                                    ->hiddenLabel(),
                                TextEntry::make('amount')
                                    ->hiddenLabel()
                                    ->money('EGP'),
                            ])
                            ->columnSpanFull(),
                        TextEntry::make('pricing_subtotal')
                            ->label(__('Subtotal'))
                            ->getStateUsing(fn (Order $record): mixed => data_get(OrderAdminSupport::pricingSummary($record), 'subtotal'))
                            ->formatStateUsing(fn (mixed $state, Order $record): string => OrderAdminSupport::money($state, $record->currency))
                            ->visible(fn (Order $record): bool => filled(data_get(OrderAdminSupport::pricingSummary($record), 'subtotal'))),
                        TextEntry::make('pricing_discount')
                            ->label(__('Discount'))
                            ->getStateUsing(fn (Order $record): mixed => data_get(OrderAdminSupport::pricingSummary($record), 'discount'))
                            ->formatStateUsing(fn (mixed $state, Order $record): string => OrderAdminSupport::money($state, $record->currency))
                            ->visible(fn (Order $record): bool => filled(data_get(OrderAdminSupport::pricingSummary($record), 'discount'))),
                        TextEntry::make('pricing_fees')
                            ->label(__('Fees'))
                            ->getStateUsing(fn (Order $record): mixed => data_get(OrderAdminSupport::pricingSummary($record), 'fees'))
                            ->formatStateUsing(fn (mixed $state, Order $record): string => OrderAdminSupport::money($state, $record->currency))
                            ->visible(fn (Order $record): bool => filled(data_get(OrderAdminSupport::pricingSummary($record), 'fees'))),
                        TextEntry::make('pricing_total')
                            ->label(__('Total'))
                            ->getStateUsing(fn (Order $record): mixed => data_get(OrderAdminSupport::pricingSummary($record), 'total'))
                            ->formatStateUsing(fn (mixed $state, Order $record): string => OrderAdminSupport::money($state, $record->currency))
                            ->visible(fn (Order $record): bool => filled(data_get(OrderAdminSupport::pricingSummary($record), 'total'))),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('Raw Gateway Data'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('charge_request')
                            ->label(__('Charge Request'))
                            ->getStateUsing(fn (Order $record): ?HtmlString => OrderAdminSupport::prettyJson(data_get(OrderAdminSupport::payload($record), 'charge_request')))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('charge_response')
                            ->label(__('Charge Response'))
                            ->getStateUsing(fn (Order $record): ?HtmlString => OrderAdminSupport::prettyJson(data_get(OrderAdminSupport::payload($record), 'charge_response')))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('payload')
                            ->label(__('Payload'))
                            ->getStateUsing(fn (Order $record): ?HtmlString => OrderAdminSupport::prettyJson(OrderAdminSupport::payload($record)))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
