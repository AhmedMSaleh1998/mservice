<?php

namespace App\Filament\Resources\Procedures\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProcedureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Procedure'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required(),
                        Textarea::make('required_documents')
                            ->label(__('Required Documents'))
                            ->rows(4)
                            ->required(),
                        Textarea::make('steps')
                            ->label(__('Steps'))
                            ->rows(4)
                            ->required(),
                        Textarea::make('conditions')
                            ->label(__('Conditions'))
                            ->rows(4)
                            ->required(),
                    ])
                    ->columnSpanFull(),
                FileUpload::make('icon_path')
                    ->label(__('Icon'))
                    ->image()
                    ->directory('procedures/icons')
                    ->disk('public')
                    ->maxSize(2048)
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('file_path')
                    ->label(__('Attachment'))
                    ->directory('procedures')
                    ->disk('public')
                    ->acceptedFileTypes([
                        'application/pdf',
                    ])
                    ->required()
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
                Checkbox::make('is_active')
                    ->label(__('Is Active'))
                    ->default(true),
            ]);
    }
}
