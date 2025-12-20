<?php

namespace App\Filament\Resources\Services\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('anyLabel')
                    ->schema([
                        TextInput::make("title")->required(),
                        Textarea::make("description")->required(),
                    ])
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('icon')
                    ->collection('icon')
                    ->directory('services')
                    ->columnSpanFull(),
                Checkbox::make('is_active'),
                Checkbox::make('is_featured')
            ]);
    }
}
