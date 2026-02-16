<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('User Information'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->nullable()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('national_id')
                            ->label(__('National ID'))
                            ->maxLength(50),
                        TextInput::make('reg_number')
                            ->label(__('Registration Number'))
                            ->maxLength(50),
                        Select::make('lang')
                            ->label(__('Language'))
                            ->options([
                                'ar' => __('Arabic'),
                                'en' => __('English'),
                            ])
                            ->default('ar')
                            ->required(),
                        Toggle::make('notification_enabled')
                            ->label(__('Notification Enabled'))
                            ->default(false),
                        Toggle::make('active')
                            ->label(__('Active'))
                            ->default(false),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                        TextInput::make('password_confirmation')
                            ->label(__('Confirm Password'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false),
                        Textarea::make('address')
                            ->label(__('Address'))
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('neqaba_address')
                            ->label(__('Neqaba Address'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
