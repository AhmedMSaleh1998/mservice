<?php

namespace App\Filament\Resources\ResetUnits\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResetUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Rest Unit'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required(),

                        TextInput::make('address')
                            ->label(__('Address'))
                            ->required(),
                    ])
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('cover_image')
                    ->label(__('Image'))
                    ->collection('cover_image')
                    ->directory('rest-units')
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label(__('Gallery'))
                    ->collection('gallery')
                    ->multiple()
                    ->directory('rest-units')
                    ->columnSpanFull(),

                Select::make('province_id')
                    ->relationship('province', 'name')
                    ->label(__('Province'))
                    ->required()
                    ->columnSpanFull(),

                Grid::make(3)
                    ->schema([
                        Section::make(__('Single Rooms'))
                            ->schema([
                                TextInput::make('single_rooms')
                                    ->label(__('Count'))
                                    ->numeric()
                                    ->required(),

                                TextInput::make('single_room_price')
                                    ->label(__('Price'))
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->default(0),
                            ]),

                        Section::make(__('Double Rooms'))
                            ->schema([
                                TextInput::make('double_rooms')
                                    ->label(__('Count'))
                                    ->numeric()
                                    ->required(),

                                TextInput::make('double_room_price')
                                    ->label(__('Price'))
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->default(0),
                            ]),

                        Section::make(__('Triple Rooms'))
                            ->schema([
                                TextInput::make('triple_rooms')
                                    ->label(__('Count'))
                                    ->numeric()
                                    ->required(),

                                TextInput::make('triple_room_price')
                                    ->label(__('Price'))
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->default(0),
                            ]),
                    ])
                    ->columnSpanFull(),

                Checkbox::make('is_active')
                    ->label(__('Is Active'))
                    ->columnSpanFull(),
            ]);
    }
}
