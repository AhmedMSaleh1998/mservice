<?php

namespace App\Filament\Resources\AdSpaces\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Services\Models\Service;

class AdSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_id')
                    ->label(__('Service'))
                    ->options(fn () => Service::query()
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Service $service) => [
                            $service->id => $service->getTranslation('title', app()->getLocale())
                                ?: ($service->getTranslation('title', 'en') ?: ($service->key ?? (string) $service->id)),
                        ])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('max_characters')
                    ->label(__('Max Characters'))
                    ->numeric()
                    ->minValue(0)
                    ->minValue(1),
                Select::make('min_duration_months')
                    ->label(__('Minimum Months'))
                    ->options([
                        1 => __('One Month'),
                        2 => __('Two Months'),
                        3 => __('Three Months'),
                    ])
                    ->default(1),
                TextInput::make('price_per_month')
                    ->label(__('Price Per Month'))
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1),
                TextInput::make('order')
                    ->label(__('Order'))
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ])
            ->columns(2);
    }
}
