<?php

namespace App\Filament\Resources\Pages\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('content')
                    ->schema([
                        TextInput::make('title')->label(__('Title'))->required(),
                        RichEditor::make('content')
                            ->label(__('Content'))
                            ->extraAttributes(['style' => 'min-height: 400px;'])
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
