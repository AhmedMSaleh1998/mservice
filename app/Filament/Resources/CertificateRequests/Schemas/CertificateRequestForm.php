<?php

namespace App\Filament\Resources\CertificateRequests\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Certificates\Models\CertificateRequest;

class CertificateRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Certificate Request'))
                    ->schema([
                        Placeholder::make('user_name')
                            ->label(__('User'))
                            ->content(fn (?CertificateRequest $record) => $record?->user?->name ?? '-'),
                        Placeholder::make('certificate_name')
                            ->label(__('Certificate'))
                            ->content(function (?CertificateRequest $record): string {
                                if (! $record?->certificate) {
                                    return '-';
                                }

                                return (string) $record->certificate->getTranslation('name', app()->getLocale());
                            }),
                        Placeholder::make('delivery_method')
                            ->label(__('Delivery Method'))
                            ->content(function (?CertificateRequest $record): string {
                                return match ($record?->delivery_method) {
                                    'digital' => __('Digital'),
                                    'delivery' => __('Delivery'),
                                    null, '' => '-',
                                    default => (string) $record?->delivery_method,
                                };
                            }),
                        Placeholder::make('order_payment_method')
                            ->label(__('Payment Method'))
                            ->content(fn (?CertificateRequest $record) => $record?->order?->payment_method ?? '-'),
                        Placeholder::make('phone')
                            ->label(__('Phone'))
                            ->content(fn (?CertificateRequest $record) => $record?->phone ?: '-'),
                        Placeholder::make('email')
                            ->label(__('Email'))
                            ->content(fn (?CertificateRequest $record) => $record?->email ?: '-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Address'))
                    ->schema([
                        Placeholder::make('address_name')
                            ->label(__('Address Name'))
                            ->content(fn (?CertificateRequest $record) => $record?->userAddress?->address_name ?? '-'),
                        Placeholder::make('province')
                            ->label(__('Province'))
                            ->content(function (?CertificateRequest $record): string {
                                $province = $record?->userAddress?->province;

                                return $province
                                    ? (string) $province->getTranslation('name', app()->getLocale())
                                    : '-';
                            }),
                        Placeholder::make('district')
                            ->label(__('District'))
                            ->content(fn (?CertificateRequest $record) => $record?->userAddress?->district ?? '-'),
                        Placeholder::make('street')
                            ->label(__('Street'))
                            ->content(fn (?CertificateRequest $record) => $record?->userAddress?->street ?? '-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Costs'))
                    ->schema([
                        TextInput::make('printing_cost')
                            ->label(__('Printing Cost'))
                            ->prefix('EGP')
                            ->disabled(),
                        TextInput::make('delivery_cost')
                            ->label(__('Shipping Cost'))
                            ->prefix('EGP')
                            ->disabled(),
                        TextInput::make('subscription_cost')
                            ->label(__('Subscription Cost'))
                            ->prefix('EGP')
                            ->disabled(),
                        TextInput::make('total_amount')
                            ->label(__('Total Amount'))
                            ->prefix('EGP')
                            ->disabled(),
                    ])
                    ->columns(2),
                Section::make(__('Status'))
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(CertificateRequest::statusOptions())
                            ->required(),
                        Select::make('delivery_status')
                            ->label(__('Delivery Status'))
                            ->options(CertificateRequest::deliveryStatusOptions())
                            ->nullable()
                            ->visible(fn (?CertificateRequest $record) => $record?->delivery_method === 'delivery'),
                    ])
                    ->columns(2),
            ]);
    }
}
