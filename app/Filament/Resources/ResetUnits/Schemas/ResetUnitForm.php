<?php

namespace App\Filament\Resources\ResetUnits\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Services\Models\RestUnit;

class ResetUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Rest Unit Type'))
                    ->description(__('Choose the rest unit type first — it decides how places and maintenance are managed.'))
                    ->schema([
                        Select::make('type')
                            ->label(__('Type'))
                            ->options(RestUnit::typeOptions())
                            ->default(RestUnit::TYPE_BEDS)
                            ->required()
                            ->live()
                            ->native(false)
                            ->disabledOn('edit')
                            ->helperText(fn (Get $get): string => match ($get('type')) {
                                RestUnit::TYPE_ROOMS => __('Each room is a separate unit you can book and maintain individually.'),
                                RestUnit::TYPE_WHOLE_UNIT => __('The whole unit is booked as one place.'),
                                default => __('Each bed is a separate unit you can send to maintenance individually.'),
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                TranslatableTabs::make(__('Rest Unit'))
                    ->schema([
                        TextInput::make('name')->label(__('Name'))->required(),
                        TextInput::make('address')->label(__('Address'))->required(),
                        Textarea::make('description')->label(__('Description'))->rows(3),
                    ])
                    ->columnSpanFull(),

                SpatieMediaLibraryFileUpload::make('cover_image')
                    ->label(__('Image'))
                    ->collection('cover_image')
                    ->directory('rest-units')
                    ->downloadable()
                    ->previewable()
                    ->columnSpanFull(),

                Select::make('province_id')
                    ->relationship('province', 'name')
                    ->label(__('Province'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                // Oracle "pand" (item) number sent to Oracle when a rest unit payment is synced.
                TextInput::make('pand_id')
                    ->label(__('Oracle item number'))
                    ->helperText(__('The item (pand) number registered in Oracle for this rest unit.'))
                    ->numeric()
                    ->minValue(1)
                    ->columnSpanFull(),

                // Shared price for beds (per bed) and whole unit (per unit); rooms price is per room.
                TextInput::make('price')
                    ->label(fn (Get $get): string => $get('type') === RestUnit::TYPE_BEDS
                        ? __('Price per bed / night')
                        : __('Price per night'))
                    ->numeric()->prefix('EGP')->default(0)->required()
                    ->visible(fn (Get $get): bool => in_array($get('type'), [RestUnit::TYPE_BEDS, RestUnit::TYPE_WHOLE_UNIT], true))
                    ->columnSpanFull(),

                // ---- Type: BEDS ----
                Section::make(__('Beds'))
                    ->visible(fn (Get $get): bool => $get('type') === RestUnit::TYPE_BEDS)
                    ->schema([
                        TextInput::make('beds_total')
                            ->label(__('Total beds'))
                            ->helperText(__('Beds are generated automatically. Send specific beds to maintenance from the Beds tab after saving.'))
                            ->numeric()->minValue(0)->default(0)
                            ->dehydrated(false)
                            ->visibleOn('create'),
                        Placeholder::make('beds_edit_hint')
                            ->hiddenLabel()
                            ->content(__('Manage beds and their maintenance from the Beds tab.'))
                            ->visibleOn('edit'),
                    ])
                    ->columnSpanFull(),

                // ---- Type: ROOMS ----
                Section::make(__('Rooms'))
                    ->visible(fn (Get $get): bool => $get('type') === RestUnit::TYPE_ROOMS)
                    ->schema([
                        Placeholder::make('rooms_hint')
                            ->hiddenLabel()
                            ->content(__('Save first, then add rooms and manage their maintenance from the Rooms tab.')),
                    ])
                    ->columnSpanFull(),

                // ---- Type: WHOLE UNIT ----
                Section::make(__('Whole Unit'))
                    ->visible(fn (Get $get): bool => $get('type') === RestUnit::TYPE_WHOLE_UNIT)
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                RestUnit::STATUS_IN_SERVICE => __('In service'),
                                RestUnit::STATUS_MAINTENANCE => __('Under maintenance'),
                            ])
                            ->default(RestUnit::STATUS_IN_SERVICE)
                            ->native(false)
                            ->live()
                            ->required(),
                        Textarea::make('maintenance_note')
                            ->label(__('Maintenance note'))
                            ->visible(fn (Get $get): bool => $get('status') === RestUnit::STATUS_MAINTENANCE)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Checkbox::make('is_active')
                    ->label(__('Is Active'))
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }
}
