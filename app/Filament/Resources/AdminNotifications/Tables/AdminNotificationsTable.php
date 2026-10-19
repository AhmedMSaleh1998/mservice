<?php

namespace App\Filament\Resources\AdminNotifications\Tables;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            // Keeps the counters moving while a notification is being sent.
            ->poll('10s')
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('channels')
                    ->label(__('Channels'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminNotificationResource::channelLabel($state)),
                TextColumn::make('audience')
                    ->label(__('Recipients'))
                    ->formatStateUsing(fn (?string $state): string => AdminNotificationResource::audienceLabel($state)),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AdminNotificationResource::statusLabel($state))
                    ->color(fn (?string $state): string => AdminNotificationResource::statusColor($state))
                    ->sortable(),
                TextColumn::make('recipients_count')
                    ->label(__('Recipients Count'))
                    ->numeric(),
                TextColumn::make('fcm_sent_count')
                    ->label(__('App notifications sent'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('sms_sent_count')
                    ->label(__('SMS sent'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label(__('Created By'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
