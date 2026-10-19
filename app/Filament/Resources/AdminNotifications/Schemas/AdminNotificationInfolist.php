<?php

namespace App\Filament\Resources\AdminNotifications\Schemas;

use App\Filament\Resources\AdminNotifications\AdminNotificationResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Notifications\Models\AdminNotification;
use Modules\Users\Models\User;

class AdminNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Notification Content'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('Title')),
                        TextEntry::make('body')
                            ->label(__('Message'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('Sending Options'))
                    ->schema([
                        TextEntry::make('channels')
                            ->label(__('Channels'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => AdminNotificationResource::channelLabel($state)),
                        TextEntry::make('audience')
                            ->label(__('Recipients'))
                            ->formatStateUsing(fn (?string $state): string => AdminNotificationResource::audienceLabel($state)),
                        TextEntry::make('user_ids')
                            ->label(__('Users'))
                            ->getStateUsing(fn (AdminNotification $record): array => User::query()
                                ->whereIn('id', $record->user_ids ?? [])
                                ->pluck('name')
                                ->all())
                            ->badge()
                            ->visible(fn (AdminNotification $record): bool => $record->audience === AdminNotification::AUDIENCE_USERS)
                            ->columnSpanFull(),
                        TextEntry::make('registration_numbers')
                            ->label(__('Registration numbers'))
                            ->badge()
                            ->visible(fn (AdminNotification $record): bool => $record->audience === AdminNotification::AUDIENCE_REGISTRATION_NUMBERS)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make(__('Sending Results'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => AdminNotificationResource::statusLabel($state))
                            ->color(fn (?string $state): string => AdminNotificationResource::statusColor($state)),
                        TextEntry::make('recipients_count')
                            ->label(__('Recipients Count')),
                        TextEntry::make('fcm_sent_count')
                            ->label(__('App notifications sent')),
                        TextEntry::make('fcm_failed_count')
                            ->label(__('App notifications failed')),
                        TextEntry::make('sms_sent_count')
                            ->label(__('SMS sent')),
                        TextEntry::make('sms_failed_count')
                            ->label(__('SMS failed')),
                        TextEntry::make('creator.name')
                            ->label(__('Created By')),
                        TextEntry::make('queued_at')
                            ->label(__('Sent At'))
                            ->dateTime(),
                        TextEntry::make('completed_at')
                            ->label(__('Completed At'))
                            ->dateTime(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
