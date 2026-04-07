<?php

namespace App\Filament\Resources\CertificateRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\PaymentMethod;

class CertificateRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Certificate Request'))
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('ID'))
                            ->copyable(),
                        TextEntry::make('user.name')
                            ->label(__('User'))
                            ->placeholder('-'),
                        TextEntry::make('certificate_name')
                            ->label(__('Certificate'))
                            ->getStateUsing(function (CertificateRequest $record): string {
                                if (! $record->certificate) {
                                    return '-';
                                }

                                return (string) $record->certificate->getTranslation('name', app()->getLocale());
                            }),
                        TextEntry::make('delivery_method')
                            ->label(__('Delivery Method'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'digital' => __('Digital'),
                                'delivery' => __('Delivery'),
                                null, '' => '-',
                                default => (string) $state,
                            })
                            ->color(fn (?string $state): string => $state === 'delivery' ? 'info' : 'gray'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => CertificateRequest::statusOptions()[$state] ?? $state ?? '-')
                            ->color(fn (?string $state): string => static::statusColor($state)),
                        TextEntry::make('delivery_status')
                            ->label(__('Delivery Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state ? (CertificateRequest::deliveryStatusOptions()[$state] ?? $state) : '-')
                            ->color(fn (?string $state): string => static::deliveryStatusColor($state))
                            ->visible(fn (CertificateRequest $record): bool => $record->delivery_method === 'delivery'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('Contact Details'))
                    ->schema([
                        TextEntry::make('phone')
                            ->label(__('Phone'))
                            ->placeholder('-'),
                        TextEntry::make('email')
                            ->label(__('Email'))
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Address'))
                    ->schema([
                        TextEntry::make('userAddress.address_name')
                            ->label(__('Address Name'))
                            ->placeholder('-'),
                        TextEntry::make('province')
                            ->label(__('Province'))
                            ->getStateUsing(function (CertificateRequest $record): string {
                                $province = $record->userAddress?->province;

                                return $province
                                    ? (string) $province->getTranslation('name', app()->getLocale())
                                    : '-';
                            }),
                        TextEntry::make('userAddress.district')
                            ->label(__('District'))
                            ->placeholder('-'),
                        TextEntry::make('userAddress.street')
                            ->label(__('Street'))
                            ->placeholder('-'),
                        TextEntry::make('userAddress.unit_number')
                            ->label(__('Unit Number'))
                            ->placeholder('-'),
                        TextEntry::make('userAddress.phone')
                            ->label(__('Address Phone'))
                            ->placeholder('-'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Costs'))
                    ->schema([
                        TextEntry::make('printing_cost')
                            ->label(__('Printing Cost'))
                            ->money('EGP'),
                        TextEntry::make('delivery_cost')
                            ->label(__('Shipping Cost'))
                            ->money('EGP'),
                        TextEntry::make('subscription_cost')
                            ->label(__('Subscription Cost'))
                            ->money('EGP'),
                        TextEntry::make('total_amount')
                            ->label(__('Total Amount'))
                            ->money('EGP'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make(__('Payment / Order Details'))
                    ->schema([
                        TextEntry::make('order_notice')
                            ->label(__('Payment / Order Details'))
                            ->hiddenLabel()
                            ->getStateUsing(fn (): string => __('No payment order has been linked yet.'))
                            ->visible(fn (CertificateRequest $record): bool => ! $record->order)
                            ->columnSpanFull(),
                        TextEntry::make('order.id')
                            ->label(__('Order ID'))
                            ->copyable()
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.status')
                            ->label(__('Order Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => CertificateRequest::statusOptions()[$state] ?? $state ?? '-')
                            ->color(fn (?string $state): string => static::statusColor($state))
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.payment_method')
                            ->label(__('Payment Method'))
                            ->getStateUsing(fn (CertificateRequest $record): ?string => static::paymentMethodLabel($record->order?->payment_method))
                            ->placeholder('-')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.amount')
                            ->label(__('Order Amount'))
                            ->money('EGP')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.provider')
                            ->label(__('Provider'))
                            ->placeholder('-')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.merchant_ref_num')
                            ->label(__('Merchant Reference'))
                            ->placeholder('-')
                            ->copyable()
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.gateway_reference')
                            ->label(__('Gateway Reference'))
                            ->placeholder('-')
                            ->copyable()
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.gateway_status')
                            ->label(__('Gateway Status'))
                            ->placeholder('-')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.paid_at')
                            ->label(__('Paid At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.payment_started_at')
                            ->label(__('Payment Started At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                        TextEntry::make('order.payment_last_synced_at')
                            ->label(__('Last Synced At'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (CertificateRequest $record): bool => (bool) $record->order),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make(__('Raw Gateway Data'))
                    ->visible(fn (CertificateRequest $record): bool => (bool) $record->order)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('charge_request')
                            ->label(__('Charge Request'))
                            ->getStateUsing(fn (CertificateRequest $record): ?HtmlString => static::prettyJson(data_get($record->order?->payload, 'charge_request')))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('charge_response')
                            ->label(__('Charge Response'))
                            ->getStateUsing(fn (CertificateRequest $record): ?HtmlString => static::prettyJson(data_get($record->order?->payload, 'charge_response')))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('payload')
                            ->label(__('Payload'))
                            ->getStateUsing(fn (CertificateRequest $record): ?HtmlString => static::prettyJson($record->order?->payload))
                            ->placeholder(__('No gateway payload available.'))
                            ->copyable()
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            CertificateRequest::STATUS_PENDING_PAYMENT => 'warning',
            CertificateRequest::STATUS_PAID_SUCCESSFULLY => 'info',
            CertificateRequest::STATUS_PROCESSING => 'primary',
            CertificateRequest::STATUS_COMPLETED => 'success',
            CertificateRequest::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    private static function deliveryStatusColor(?string $status): string
    {
        return match ($status) {
            CertificateRequest::DELIVERY_STATUS_PENDING => 'warning',
            CertificateRequest::DELIVERY_STATUS_PREPARING => 'info',
            CertificateRequest::DELIVERY_STATUS_SHIPPED => 'primary',
            CertificateRequest::DELIVERY_STATUS_DELIVERED => 'success',
            CertificateRequest::DELIVERY_STATUS_FAILED,
            CertificateRequest::DELIVERY_STATUS_RETURNED => 'danger',
            default => 'gray',
        };
    }

    private static function paymentMethodLabel(?string $key): ?string
    {
        if (! $key) {
            return null;
        }

        $method = PaymentMethod::query()->where('key', $key)->first();

        if (! $method) {
            return $key;
        }

        return $method->getTranslation('name', app()->getLocale());
    }

    private static function prettyJson(mixed $value): ?HtmlString
    {
        if (blank($value)) {
            return null;
        }

        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return null;
        }

        return new HtmlString(sprintf('<pre class="text-xs">%s</pre>', e($json)));
    }
}
