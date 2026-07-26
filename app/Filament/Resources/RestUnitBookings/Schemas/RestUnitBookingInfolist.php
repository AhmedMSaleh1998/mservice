<?php

namespace App\Filament\Resources\RestUnitBookings\Schemas;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Services\OrderService;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;

class RestUnitBookingInfolist
{
    private static array $paymentMethodLabels = [];

    private static array $pricingSummaryCache = [];

    private static array $subscriptionSnapshotCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Booking Summary'))
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('ID'))
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => RestUnitBookingResource::getStatusLabel($state))
                            ->color(fn (?string $state): string => RestUnitBookingResource::getStatusColor($state)),
                        TextEntry::make('target')
                            ->label(__('Type'))
                            ->badge()
                            ->getStateUsing(fn (RestUnitBooking $record): string => $record->targetLabel())
                            ->color('info'),
                        TextEntry::make('start_date')
                            ->label(__('Start Date'))
                            ->date(),
                        TextEntry::make('end_date')
                            ->label(__('End Date'))
                            ->date(),
                        TextEntry::make('nights')
                            ->label(__('Nights'))
                            ->getStateUsing(fn (RestUnitBooking $record): int => static::bookingNights($record)),
                        TextEntry::make('total_price')
                            ->label(__('Total Amount'))
                            ->money('EGP'),
                        TextEntry::make('paid_at')
                            ->label(__('Paid At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),
                        TextEntry::make('cancellation_reason')
                            ->label(__('Cancellation reason'))
                            ->visible(fn (RestUnitBooking $record): bool => $record->isCancelled())
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Martyr Family Beneficiary'))
                    ->visible(fn (RestUnitBooking $record): bool => $record->isForMartyrFamily())
                    ->schema([
                        TextEntry::make('beneficiary_card_number')
                            ->label(__('National ID'))
                            ->placeholder('-'),
                        TextEntry::make('beneficiary_name')
                            ->label(__('Beneficiary name'))
                            ->placeholder('-'),
                        TextEntry::make('payment_reference')
                            ->label(__('Transaction number'))
                            ->placeholder('-'),
                        SpatieMediaLibraryImageEntry::make('payment_receipt')
                            ->label(__('Transfer image'))
                            ->collection(RestUnitBooking::RECEIPT_COLLECTION)
                            ->placeholder(__('No image uploaded.'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('User Details'))
                    ->visible(fn (RestUnitBooking $record): bool => ! $record->isForMartyrFamily())
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(__('Name'))
                            ->placeholder('-'),
                        TextEntry::make('user.email')
                            ->label(__('Email'))
                            ->placeholder('-'),
                        TextEntry::make('user.phone')
                            ->label(__('Phone'))
                            ->placeholder('-'),
                        TextEntry::make('user.national_id')
                            ->label(__('National ID'))
                            ->placeholder('-'),
                        TextEntry::make('user.reg_number')
                            ->label(__('Registration Number'))
                            ->placeholder('-'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Rest Unit Details'))
                    ->schema([
                        TextEntry::make('rest_unit_name')
                            ->label(__('Name'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?string => static::restUnitName($record))
                            ->placeholder('-'),
                        TextEntry::make('rest_unit_province')
                            ->label(__('Province'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?string => static::restUnitProvinceName($record))
                            ->placeholder('-'),
                        IconEntry::make('rest_unit_is_active')
                            ->label(__('Is Active'))
                            ->getStateUsing(fn (RestUnitBooking $record): bool => (bool) $record->restUnit?->is_active)
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('rest_unit_address')
                            ->label(__('Address'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?string => static::restUnitAddress($record))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('rest_unit_type')
                            ->label(__('Type'))
                            ->getStateUsing(fn (RestUnitBooking $record): string => RestUnit::typeLabel($record->restUnit?->type)),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Payment / Order Details'))
                    ->schema([
                        TextEntry::make('payment_order_notice')
                            ->label(__('Payment / Order Details'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No payment order has been linked yet.'))
                            ->visible(fn (RestUnitBooking $record): bool => ! static::hasOrder($record))
                            ->columnSpanFull(),
                        TextEntry::make('order.id')
                            ->label(__('ID'))
                            ->copyable()
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => RestUnitBookingResource::getStatusLabel($state))
                            ->color(fn (?string $state): string => RestUnitBookingResource::getStatusColor($state))
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.amount')
                            ->label(__('Total Amount'))
                            ->money('EGP')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.currency')
                            ->label(__('Currency'))
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.payment_method')
                            ->label(__('Payment Method'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?string => static::paymentMethodLabel($record->order?->payment_method))
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.provider')
                            ->label(__('Provider'))
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.merchant_ref_num')
                            ->label(__('Merchant Reference'))
                            ->copyable()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.gateway_reference')
                            ->label(__('Gateway Reference'))
                            ->copyable()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.gateway_status')
                            ->label(__('Gateway Status'))
                            ->badge()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.checkout_url')
                            ->label(__('Payment URL'))
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->copyable()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.payment_started_at')
                            ->label(__('Payment Started At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.payment_last_synced_at')
                            ->label(__('Last Synced At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                        TextEntry::make('order.paid_at')
                            ->label(__('Paid At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record)),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Pricing Breakdown'))
                    ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record))
                    ->schema([
                        TextEntry::make('pricing_notice')
                            ->label(__('Pricing Breakdown'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No pricing breakdown available.'))
                            ->visible(fn (RestUnitBooking $record): bool => ! static::hasPricing($record))
                            ->columnSpanFull(),
                        RepeatableEntry::make('pricing_items')
                            ->label(__('Pricing Breakdown'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (RestUnitBooking $record): array => static::pricingItems($record))
                            ->visible(fn (RestUnitBooking $record): bool => static::hasPricing($record))
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
                        TextEntry::make('pricing_currency')
                            ->label(__('Currency'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?string => data_get(static::pricingSummary($record), 'currency'))
                            ->visible(fn (RestUnitBooking $record): bool => static::hasPricing($record)),
                        TextEntry::make('pricing_subtotal')
                            ->label(__('Subtotal'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?float => static::pricingAmount($record, 'subtotal'))
                            ->money('EGP')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasPricing($record)),
                        TextEntry::make('pricing_discount')
                            ->label(__('Discount'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?float => static::pricingAmount($record, 'discount'))
                            ->money('EGP')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasPricing($record)),
                        TextEntry::make('pricing_fees')
                            ->label(__('Fees'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?float => static::pricingAmount($record, 'fees'))
                            ->money('EGP')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasPricing($record)),
                        TextEntry::make('pricing_total')
                            ->label(__('Total'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?float => static::pricingAmount($record, 'total'))
                            ->money('EGP')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasPricing($record)),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('Subscription Snapshot'))
                    ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record))
                    ->schema([
                        TextEntry::make('subscription_notice')
                            ->label(__('Subscription Snapshot'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No subscription snapshot available.'))
                            ->visible(fn (RestUnitBooking $record): bool => ! static::hasSubscriptionSnapshot($record))
                            ->columnSpanFull(),
                        TextEntry::make('subscription_register_no')
                            ->label(__('Registration Number'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?string => data_get(static::subscriptionSnapshot($record), 'register_no'))
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasSubscriptionSnapshot($record)),
                        TextEntry::make('subscription_amount')
                            ->label(__('Total Amount'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?float => static::toFloat(data_get(static::subscriptionSnapshot($record), 'amount')))
                            ->money('EGP')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasSubscriptionSnapshot($record)),
                        TextEntry::make('subscription_years')
                            ->label(__('Years'))
                            ->getStateUsing(fn (RestUnitBooking $record): mixed => data_get(static::subscriptionSnapshot($record), 'years'))
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasSubscriptionSnapshot($record)),
                        TextEntry::make('subscription_status')
                            ->label(__('Status'))
                            ->getStateUsing(fn (RestUnitBooking $record): mixed => data_get(static::subscriptionSnapshot($record), 'status'))
                            ->placeholder('-')
                            ->visible(fn (RestUnitBooking $record): bool => static::hasSubscriptionSnapshot($record)),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('Raw Gateway Data'))
                    ->visible(fn (RestUnitBooking $record): bool => static::hasOrder($record))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('charge_request')
                            ->label(__('Charge Request'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?HtmlString => static::prettyJson(data_get(static::payload($record), 'charge_request')))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('charge_response')
                            ->label(__('Charge Response'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?HtmlString => static::prettyJson(data_get(static::payload($record), 'charge_response')))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('payload')
                            ->label(__('Payload'))
                            ->getStateUsing(fn (RestUnitBooking $record): ?HtmlString => static::prettyJson(static::payload($record)))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function bookingNights(RestUnitBooking $record): int
    {
        $startDate = $record->start_date;
        $endDate = $record->end_date;

        if (! $startDate || ! $endDate) {
            return 0;
        }

        return max($startDate->diffInDays($endDate), 1);
    }

    private static function hasOrder(RestUnitBooking $record): bool
    {
        return static::order($record) !== null;
    }

    private static function order(RestUnitBooking $record): ?Order
    {
        return $record->order;
    }

    private static function pricingSummary(RestUnitBooking $record): ?array
    {
        $order = static::order($record);

        if (! $order) {
            return null;
        }

        $cacheKey = static::orderCacheKey($order);

        if (array_key_exists($cacheKey, static::$pricingSummaryCache)) {
            return static::$pricingSummaryCache[$cacheKey];
        }

        return static::$pricingSummaryCache[$cacheKey] = app(OrderService::class)->pricingSummary($order);
    }

    private static function hasPricing(RestUnitBooking $record): bool
    {
        $items = data_get(static::pricingSummary($record), 'items', []);

        return is_array($items) && $items !== [];
    }

    private static function pricingItems(RestUnitBooking $record): array
    {
        $items = data_get(static::pricingSummary($record), 'items', []);

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? '-'),
                'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
                'unit_price' => static::toFloat($item['unit_price'] ?? null),
                'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                'amount' => static::toFloat($item['amount'] ?? null),
            ])
            ->values()
            ->all();
    }

    private static function pricingAmount(RestUnitBooking $record, string $key): ?float
    {
        return static::toFloat(data_get(static::pricingSummary($record), $key));
    }

    private static function subscriptionSnapshot(RestUnitBooking $record): ?array
    {
        $order = static::order($record);

        if (! $order) {
            return null;
        }

        $cacheKey = static::orderCacheKey($order);

        if (array_key_exists($cacheKey, static::$subscriptionSnapshotCache)) {
            return static::$subscriptionSnapshotCache[$cacheKey];
        }

        return static::$subscriptionSnapshotCache[$cacheKey] = app(OrderService::class)->subscriptionChargeSnapshot($order);
    }

    private static function hasSubscriptionSnapshot(RestUnitBooking $record): bool
    {
        return static::subscriptionSnapshot($record) !== null;
    }

    private static function payload(RestUnitBooking $record): ?array
    {
        $payload = static::order($record)?->payload;

        return is_array($payload) ? $payload : null;
    }

    private static function paymentMethodLabel(?string $key): ?string
    {
        if (! filled($key)) {
            return null;
        }

        $cacheKey = app()->getLocale() . ':' . $key;

        if (array_key_exists($cacheKey, static::$paymentMethodLabels)) {
            return static::$paymentMethodLabels[$cacheKey];
        }

        $method = PaymentMethod::query()->where('key', $key)->first();

        if (! $method) {
            return static::$paymentMethodLabels[$cacheKey] = $key;
        }

        return static::$paymentMethodLabels[$cacheKey] = (string) $method->getTranslation('name', app()->getLocale());
    }

    private static function restUnitName(RestUnitBooking $record): ?string
    {
        $restUnit = $record->restUnit;

        if (! $restUnit instanceof RestUnit) {
            return null;
        }

        return (string) $restUnit->getTranslation('name', app()->getLocale());
    }

    private static function restUnitAddress(RestUnitBooking $record): ?string
    {
        $restUnit = $record->restUnit;

        if (! $restUnit instanceof RestUnit) {
            return null;
        }

        return (string) $restUnit->getTranslation('address', app()->getLocale());
    }

    private static function restUnitProvinceName(RestUnitBooking $record): ?string
    {
        $province = $record->restUnit?->province;

        if (! $province) {
            return null;
        }

        return method_exists($province, 'getTranslation')
            ? (string) $province->getTranslation('name', app()->getLocale())
            : ($province->name ?? null);
    }

    private static function toFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private static function orderCacheKey(Order $order): string
    {
        return implode(':', [
            (string) $order->getKey(),
            optional($order->updated_at)->format('Y-m-d H:i:s.u') ?? 'no-updated-at',
            md5(json_encode($order->payload ?? [])),
        ]);
    }

    private static function prettyJson(mixed $value): ?HtmlString
    {
        if (blank($value)) {
            return null;
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json) || $json === '') {
            return null;
        }

        return new HtmlString(sprintf(
            '<pre class="whitespace-pre-wrap break-all text-xs">%s</pre>',
            e($json),
        ));
    }
}
