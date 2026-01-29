<?php

namespace App\Filament\Resources\RegistrationRequests\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Basic Information'))
                    ->schema([
                        TextInput::make('phone')
                            ->label(__('Phone Number'))
                            ->tel()
                            ->required()
                            ->disabled(),
                        TextInput::make('national_id')
                            ->label(__('National ID'))
                            ->required()
                            ->disabled(),
                        TextInput::make('reg_code')
                            ->label(__('Registration Code'))
                            ->disabled(),
                        Toggle::make('active')
                            ->label(__('Active Status'))
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
