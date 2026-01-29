<?php

namespace App\Filament\Resources\Services\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Services\Models\ServiceType;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label(__('Key'))
                    ->unique(ignoreRecord: true)
                    ->required(),
                TranslatableTabs::make('anyLabel')
                    ->schema([
                        TextInput::make("title")
                            ->label(__('Title'))
                            ->required(),
                        Textarea::make("description")
                            ->label(__('Description'))
                            ->required(),
                    ])
                    ->columnSpanFull(),
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
                SpatieMediaLibraryFileUpload::make('icon')
                    ->label(__('Icon'))
                    ->collection('icon')
                    ->directory('services')
                    ->columnSpanFull(),
                Checkbox::make('is_active')
                    ->label(__('Is Active')),
                Checkbox::make('is_featured')
                    ->label(__('Is Featured'))
            ]);
    }
}
