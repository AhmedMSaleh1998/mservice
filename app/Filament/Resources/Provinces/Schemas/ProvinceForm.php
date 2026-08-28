<?php

namespace App\Filament\Resources\Provinces\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Models\Province;

class ProvinceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Basic Information'))
                    ->schema([
                        TranslatableTabs::make(__('Name'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label(__('Code'))
                            ->numeric()
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('active')
                            ->label(__('Active'))
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Delivery Details'))
                    ->schema([
                        TextInput::make('shipping_cost')
                            ->label(__('Shipping Cost'))
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('EGP'),
                        Select::make('delivery_region_id')
                            ->label(__('Delivery Region'))
                            ->options(Province::deliveryRegionOptions())
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
