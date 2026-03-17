<?php

namespace App\Filament\Resources\AdRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Models\PaymentMethod;

class AdRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Ad Request'))
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(__('User')),
                        TextEntry::make('adSpace.name')
                            ->label(__('Ad Space'))
                            ->formatStateUsing(fn ($state, $record) => $record->adSpace?->getTranslation('name', app()->getLocale())),
                        TextEntry::make('price_per_month')
                            ->label(__('Price Per Month')),
                        TextEntry::make('duration_months')
                            ->label(__('Duration Months')),
                        TextEntry::make('total_amount')
                            ->label(__('Total Amount')),
                        TextEntry::make('ad_text')
                            ->label(__('Ad Text'))
                            ->columnSpanFull(),
                        ViewEntry::make('design_image')
                            ->label(__('Design Image'))
                            ->view('filament.infolists.ad-request-image')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Status'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status')),
                        TextEntry::make('order.payment_method')
                            ->label(__('Payment Method'))
                            ->formatStateUsing(fn ($state) => static::paymentMethodLabel($state)),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
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
}
