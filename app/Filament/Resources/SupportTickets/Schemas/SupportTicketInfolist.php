<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Ticket'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('Title')),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->formatStateUsing(fn (string $state): string => __(ucfirst($state))),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('User'))
                    ->schema([
                        TextEntry::make('user.name')
                            ->label(__('User')),
                        TextEntry::make('user.email')
                            ->label(__('Email')),
                        TextEntry::make('user.phone')
                            ->label(__('Phone')),
                    ])
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }
}
