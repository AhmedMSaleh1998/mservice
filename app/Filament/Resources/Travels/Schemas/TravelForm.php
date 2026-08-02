<?php

namespace App\Filament\Resources\Travels\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Models\Province;

class TravelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Travel Details'))
                    ->description(__('Active travels with a current or future end date are returned by the public travels endpoint.'))
                    ->schema([
                        TranslatableTabs::make(__('Travel Content'))
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('Name'))
                                    ->required(),
                                Textarea::make('description')
                                    ->label(__('Description'))
                                    ->rows(3),
                            ])
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Select::make('province_id')
                                    ->label(__('Province'))
                                    ->options(fn (): array => Province::query()
                                        ->orderBy('id')
                                        ->get()
                                        ->mapWithKeys(fn (Province $province): array => [
                                            $province->id => $province->getTranslation('name', app()->getLocale()),
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                DatePicker::make('start_date')
                                    ->label(__('Start Date'))
                                    ->required(),
                                DatePicker::make('end_date')
                                    ->label(__('End Date'))
                                    ->afterOrEqual('start_date')
                                    ->required(),
                                Checkbox::make('is_active')
                                    ->label(__('Is Active'))
                                    ->default(true),
                            ]),
                        SpatieMediaLibraryFileUpload::make('image')
                            ->label(__('Image'))
                            ->collection('image')
                            ->directory('travels')
                            ->image()
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('itinerary_file')
                            ->label(__('Detailed Travel Program File'))
                            ->collection('itinerary_file')
                            ->directory('travels/itineraries')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(10240)
                            ->preserveFilenames()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Travel Categories'))
                    ->description(__('These categories are used directly by the booking endpoint to calculate price and available seats.'))
                    ->schema([
                        Repeater::make('categories')
                            ->label(__('Categories'))
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->addActionLabel(__('Add Category'))
                            ->defaultItems(1)
                            ->cloneable()
                            ->reorderableWithButtons()
                            ->itemLabel(function (array $state): ?string {
                                $name = data_get($state, 'name.' . app()->getLocale());

                                return filled($name) ? $name : data_get($state, 'code');
                            })
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('code')
                                            ->label(__('Code'))
                                            ->required()
                                            ->distinct()
                                            ->maxLength(50),
                                        TextInput::make('price')
                                            ->label(__('Price'))
                                            ->numeric()
                                            ->prefix('EGP')
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),
                                        TextInput::make('capacity')
                                            ->label(__('Capacity'))
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),
                                        Checkbox::make('is_active')
                                            ->label(__('Is Active'))
                                            ->default(true),
                                    ]),
                                TranslatableTabs::make(__('Category Content'))
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('Name'))
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                                TagsInput::make('features')
                                    ->label(__('Features'))
                                    ->placeholder(__('Add category features'))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
