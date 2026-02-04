<?php

namespace App\Filament\Resources\AdSpaces\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Name'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required(),
                    ])
                    ->columnSpanFull(),
                TextInput::make('max_characters')
                    ->label(__('Max Characters'))
                    ->numeric()
                    ->minValue(1),
                TextInput::make('min_duration_months')
                    ->label(__('Minimum Months'))
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                TextInput::make('price_per_month')
                    ->label(__('Price Per Month'))
                    ->numeric()
                    ->required(),
                TextInput::make('order')
                    ->label(__('Order'))
                    ->numeric()
                    ->default(0),
                Toggle::make('is_available')
                    ->label(__('Is Available'))
                    ->default(true),
            ])
            ->columns(2);
    }
}
