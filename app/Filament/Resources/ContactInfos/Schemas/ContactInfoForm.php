<?php

namespace App\Filament\Resources\ContactInfos\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ContactInfoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Address'))
                    ->schema([
                        Textarea::make('address')
                            ->label(__('Address'))
                            ->rows(3)
                            ->required(),
                    ])
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('fax')
                    ->label(__('Fax'))
                    ->required()
                    ->maxLength(50),
                TagsInput::make('phones')
                    ->label(__('Phones'))
                    ->placeholder(__('Add phone numbers'))
                    ->default([])
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
