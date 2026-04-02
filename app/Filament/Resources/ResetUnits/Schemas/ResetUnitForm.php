<?php

namespace App\Filament\Resources\ResetUnits\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResetUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Rest Unit'))
                    ->schema([
                        TextInput::make('name')->label(__('Name'))->required(),
                        TextInput::make('address')->label(__('Address'))->required(),
                    ])
                    ->columnSpanFull(),
                TextInput::make('single_rooms')->label(__('Single Rooms'))->numeric()->required()->columnSpanFull(),
                TextInput::make('single_room_price')->label(__('Single Room Price'))->numeric()->prefix('EGP')->default(0)->columnSpanFull(),
                TextInput::make('double_rooms')->label(__('Double Rooms'))->numeric()->required()->columnSpanFull(),
                TextInput::make('double_room_price')->label(__('Double Room Price'))->numeric()->prefix('EGP')->default(0)->columnSpanFull(),
                TextInput::make('single_bed')->label(__('Single Beds'))->numeric()->required()->columnSpanFull(),
                TextInput::make('single_bed_price')->label(__('Single Bed Price'))->numeric()->prefix('EGP')->default(0)->columnSpanFull(),
                Select::make('province_id')
                    ->relationship('province', 'name')
                    ->label(__('Province'))
                    ->required(),
                Checkbox::make('is_active')->label(__('Is Active'))->columnSpanFull()
            ]);
    }
}
