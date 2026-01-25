<?php

namespace App\Filament\Resources\MedicalGuides\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Forms\Components\LocationPicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MedicalGuideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('Translations')
                    ->schema([
                        TextInput::make("title")->label(__('Title'))->required(),
                        Textarea::make("description")->label(__('Description'))->required(),
                        TextInput::make("address")->label(__('Address'))->required(),
                    ])
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('image')
                    ->label(__('Image'))
                    ->collection('image')
                    ->directory('medical-guides')
                    ->columnSpanFull(),
                Select::make('type')
                    ->label(__('Type'))
                    ->options([
                        'clinic' => __('Clinic'),
                        'hospital' => __('Hospital'),
                        'pharmacy' => __('Pharmacy'),
                        'lab' => __('Lab'),
                    ])
                    ->required(),
                
                \App\Filament\Forms\Components\LocationPicker::make('map')
                    ->latField('lat')
                    ->lngField('lng')
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('lat')->label(__('Latitude'))->numeric()->required(),
                TextInput::make('lng')->label(__('Longitude'))->numeric()->required(),
                
                Checkbox::make('is_active')->label(__('Is Active')),
                Checkbox::make('is_featured')->label(__('Is Featured'))
            ]);
    }
}
