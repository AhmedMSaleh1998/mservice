<?php

namespace App\Filament\Resources\Grades\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('Code'))
                    ->numeric()
                    ->required()
                    ->unique(ignoreRecord: true),
                TranslatableTabs::make(__('Name'))
                    ->schema([
                        TextInput::make('name')->required()->label(__('Name')),
                    ])
            ]);
    }
}
