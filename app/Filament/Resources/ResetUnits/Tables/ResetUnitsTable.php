<?php

namespace App\Filament\Resources\ResetUnits\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Services\Models\RestUnit;

class ResetUnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name')),
                TextColumn::make('address')->label(__('Address')),
                TextColumn::make('province.name')->label(__('Province')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RestUnit::typeLabel($state))
                    ->color('info'),
                TextColumn::make('total_places')
                    ->label(__('Total Places'))
                    ->state(fn (RestUnit $record): int => $record->loadMissing(['rooms', 'beds'])->totalPlaces()),
                ToggleColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('province')
                    ->relationship('province', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(RestUnit::typeOptions()),
                TernaryFilter::make('is_active')->label(__('Active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
