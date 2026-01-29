<?php

namespace App\Filament\Resources\MedicalUniversities\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MedicalUniversityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make(__('Name'))
                    ->schema([
                        TextInput::make('name')->required(),
                    ])
            ]);
    }
}
