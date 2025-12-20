<?php

namespace App\Filament\Resources\Blogs\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Schemas\Schema;

class BlogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('anyLabel')
                    ->schema([
                        TextInput::make("title")->required(),
                        Textarea::make("description")->required(),
                        RichEditor::make("content")
                            ->extraAttributes([
                                'style' => 'min-height: 300px;',
                            ])->required(),
                    ])
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('image')
                    ->collection('image')
                    ->directory('blogs')
                    ->columnSpanFull(),
                Checkbox::make('is_published')
            ]);
    }
}
