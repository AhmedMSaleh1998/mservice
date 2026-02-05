<?php

namespace App\Filament\Resources\MedicalGuidePlaces\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Forms\Components\LocationPicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MedicalGuidePlaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('medical_guide_id')
                    ->label(__('Doctor'))
                    ->relationship('doctor', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                TranslatableTabs::make('Translations')
                    ->schema([
                        TextInput::make('name')->label(__('Place Name'))->required(),
                        TextInput::make('address')->label(__('Address'))->required(),
                    ])
                    ->columnSpanFull(),
                TagsInput::make('phones')
                    ->label(__('Phones'))
                    ->placeholder(__('Add phone numbers')),
                LocationPicker::make('map')
                    ->latField('lat')
                    ->lngField('lng')
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('lat')->label(__('Latitude'))->numeric(),
                TextInput::make('lng')->label(__('Longitude'))->numeric(),
                Checkbox::make('is_active')->label(__('Is Active')),
            ]);
    }
}
