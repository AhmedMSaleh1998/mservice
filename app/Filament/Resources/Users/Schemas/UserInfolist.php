<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('User Details'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('reg_number')
                            ->label(__('Registration Number'))
                            ->placeholder('-'),
                        TextEntry::make('email')
                            ->label(__('Email'))
                            ->placeholder('-'),
                        TextEntry::make('phone')
                            ->label(__('Phone'))
                            ->placeholder('-'),
                        TextEntry::make('national_id')
                            ->label(__('National ID'))
                            ->placeholder('-'),
                        TextEntry::make('lang')
                            ->label(__('Language'))
                            ->badge(),
                        IconEntry::make('notification_enabled')
                            ->label(__('Notification Enabled'))
                            ->boolean(),
                        IconEntry::make('active')
                            ->label(__('Active'))
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('address')
                            ->label(__('Address'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('neqaba_address')
                            ->label(__('Neqaba Address'))
                            ->placeholder('-')
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
