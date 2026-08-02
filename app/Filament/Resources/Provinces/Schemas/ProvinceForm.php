<?php

namespace App\Filament\Resources\Provinces\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTab;
use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProvinceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('Code'))
                    ->numeric()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('shipping_cost')
                    ->label(__('Shipping Cost'))
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0)
                    ->prefix('EGP'),
                TranslatableTabs::make(__('Name'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required(),
                    ])
            ]);
    }
}
