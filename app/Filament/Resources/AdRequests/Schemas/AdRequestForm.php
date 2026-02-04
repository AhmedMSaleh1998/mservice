<?php

namespace App\Filament\Resources\AdRequests\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Models\PaymentMethod;

class AdRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Ad Request'))
                    ->schema([
                        Placeholder::make('user_name')
                            ->label(__('User'))
                            ->content(fn ($record) => $record?->user?->name ?? '-'),
                        Placeholder::make('ad_space_name')
                            ->label(__('Ad Space'))
                            ->content(function ($record) {
                                if (! $record?->adSpace) {
                                    return '-';
                                }

                                return $record->adSpace->getTranslation('name', app()->getLocale());
                            }),
                        TextInput::make('duration_months')
                            ->label(__('Duration Months'))
                            ->numeric()
                            ->disabled(),
                        TextInput::make('price_per_month')
                            ->label(__('Price Per Month'))
                            ->disabled(),
                        TextInput::make('total_amount')
                            ->label(__('Total Amount'))
                            ->disabled(),
                        Textarea::make('ad_text')
                            ->label(__('Ad Text'))
                            ->disabled()
                            ->columnSpanFull(),
                        TextInput::make('design_image_path')
                            ->label(__('Design Image Path'))
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Status'))
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'pending_payment' => __('Pending Payment'),
                                'paid_successfully' => __('Paid Successfully'),
                                'approved' => __('Approved'),
                                'rejected' => __('Rejected'),
                            ])
                            ->required(),
                        Select::make('payment_method')
                            ->label(__('Payment Method'))
                            ->options(static::paymentMethodOptions())
                            ->searchable()
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    private static function paymentMethodOptions(): array
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (PaymentMethod $method) {
                return [$method->key => $method->getTranslation('name', app()->getLocale())];
            })
            ->toArray();
    }
}
