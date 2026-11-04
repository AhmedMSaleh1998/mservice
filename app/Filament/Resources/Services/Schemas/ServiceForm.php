<?php

namespace App\Filament\Resources\Services\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Services\Models\ServiceType;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make([
                            Section::make(__('Service Details'))
                                ->icon(Heroicon::OutlinedDocumentText)
                                ->schema([
                                    TranslatableTabs::make('anyLabel')
                                        ->schema([
                                            TextInput::make('title')
                                                ->label(__('Title'))
                                                ->required(),
                                            Textarea::make('description')
                                                ->label(__('Description'))
                                                ->rows(4)
                                                ->required(),
                                        ]),
                                ]),
                            Section::make(__('Icon'))
                                ->icon(Heroicon::OutlinedPhoto)
                                ->schema([
                                    SpatieMediaLibraryFileUpload::make('icon')
                                        ->hiddenLabel()
                                        ->collection('icon')
                                        ->directory('services')
                                        ->image()
                                        ->imagePreviewHeight('120'),
                                ]),
                        ])
                            ->columnSpan(['lg' => 2]),
                        Group::make([
                            Section::make(__('Settings'))
                                ->icon(Heroicon::OutlinedCog6Tooth)
                                ->schema([
                                    Select::make('service_type_id')
                                        ->label(__('Service Type'))
                                        ->relationship('serviceType', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TranslatableTabs::make(__('Name'))
                                                ->schema([
                                                    TextInput::make('name')->required(),
                                                ]),
                                        ])
                                        ->createOptionUsing(function (array $data) {
                                            return ServiceType::create($data)->getKey();
                                        })
                                        ->required(),
                                    TextInput::make('price')
                                        ->label(__('Price'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->prefix('EGP')
                                        ->helperText(__('Used as the configurable base price for this service.')),
                                ]),
                            Section::make(__('Visibility'))
                                ->icon(Heroicon::OutlinedEye)
                                ->schema([
                                    Toggle::make('is_active')
                                        ->label(__('Is Active'))
                                        ->helperText(__('Shown to members in the app.'))
                                        ->onColor('success'),
                                    Toggle::make('is_featured')
                                        ->label(__('Is Featured'))
                                        ->onColor('warning'),
                                ]),
                        ])
                            ->columnSpan(['lg' => 1]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
