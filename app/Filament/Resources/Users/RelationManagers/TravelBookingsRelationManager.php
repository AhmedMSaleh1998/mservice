<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Support\OrderAdminSupport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TravelBookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'travelBookings';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Travel Bookings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('travel'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                TextColumn::make('travel.title')
                    ->label(__('Travel'))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('participants_count')
                    ->label(__('Participants')),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->formatStateUsing(fn (mixed $state): string => OrderAdminSupport::money($state)),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OrderAdminSupport::statusLabel($state))
                    ->color(fn (?string $state): string => OrderAdminSupport::orderStatusColor($state)),
                TextColumn::make('paid_at')
                    ->label(__('Paid At'))
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }
}
