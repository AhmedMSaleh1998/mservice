<?php

namespace App\Filament\Resources\SmsMessages\Tables;

use App\Filament\Resources\SmsMessages\SmsMessageResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Models\SmsMessage;

class SmsMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('receiver')
                    ->label(__('Receiver'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message')
                    ->label(__('Message'))
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),
                TextColumn::make('provider_status')
                    ->label(__('Provider Status'))
                    ->badge()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('message_id')
                    ->label(__('SMS ID'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->label(__('Delivered At'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        'generic' => __('Generic'),
                        'otp' => __('OTP'),
                    ]),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'pending' => __('Pending'),
                        'accepted' => __('Accepted'),
                        'delivered' => __('Delivered'),
                        'failed' => __('Failed'),
                        'reported' => __('Reported'),
                    ]),
            ])
            ->recordUrl(fn (SmsMessage $record): string => SmsMessageResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'pending' => 'warning',
            'accepted' => 'info',
            'delivered' => 'success',
            'failed' => 'danger',
            'reported' => 'gray',
            default => 'gray',
        };
    }
}
