<?php

namespace App\Filament\Resources\Certificates\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('Translations')
                    ->schema([
                        TextInput::make("name")->label(__('Name'))->required(),
                        Textarea::make("description")->label(__('Description'))->required(),
                    ])
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('image')
                    ->label(__('Image'))
                    ->collection('image')
                    ->directory('certificates')
                    ->columnSpanFull(),
                Grid::make([
                    'default' => 1,
                ])
                    ->schema([
                            TextInput::make('price')
                                ->label(__('Price'))
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('EGP'),
                        ]
                    )
                    ->columnSpanFull(),
            ]);
    }
}
