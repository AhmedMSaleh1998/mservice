<?php

namespace App\Filament\Resources\AdSpaces\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Ads\Models\AdSpace;
use Modules\Services\Models\Service;

class AdSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Service Section'))
                    ->description(__('This ad will be shown in the selected service section.'))
                    ->schema([
                        Select::make('service_id')
                            ->label(__('Service'))
                            ->options(static::serviceOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(12),
                Section::make(__('Ad Space'))
                    ->description(__('Define pricing and placement rules for this ad space.'))
                    ->schema([
                        TextInput::make('max_characters')
                            ->label(__('Max Characters'))
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->minValue(1)
                            ->helperText(__('Maximum characters allowed in ad text.')),
                        Select::make('min_duration_months')
                            ->label(__('Minimum Months'))
                            ->options(static::durationOptions())
                            ->default(1)
                            ->required(),
                        TextInput::make('price_per_month')
                            ->label(__('Price Per Month'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1)
                            ->prefix(config('checkout.currency', 'EGP')),
                        TextInput::make('order')
                            ->label(__('Order'))
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(function (?AdSpace $record): int {
                                if ($record?->exists) {
                                    return (int) $record->order;
                                }

                                return ((int) AdSpace::query()->max('order')) + 1;
                            })
                            ->helperText(__('Lower number appears first in the list.')),
                    ])
                    ->columns(2),
            ])
            ->columns(1);
    }

    private static function serviceOptions(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Service $service) => [
                $service->id => $service->getTranslation('title', app()->getLocale())
                    ?: ($service->getTranslation('title', 'en') ?: ($service->key ?? (string) $service->id)),
            ])
            ->all();
    }

    private static function durationOptions(): array
    {
        return [
            1 => __('One Month'),
            2 => __('Two Months'),
            3 => __('Three Months'),
        ];
    }
}
