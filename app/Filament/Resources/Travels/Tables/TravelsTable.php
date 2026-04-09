<?php

namespace App\Filament\Resources\Travels\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Core\Models\Province;
use Modules\Travels\Models\Travel;

class TravelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with('province')
                ->orderBy('start_date')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('Name'))
                    ->getStateUsing(fn (Travel $record): string => $record->getTranslation('title', app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('province_id')
                    ->label(__('Province'))
                    ->getStateUsing(fn (Travel $record): string => $record->province?->getTranslation('name', app()->getLocale()) ?? '-')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('Start Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('End Date'))
                    ->date()
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('province_id')
                    ->label(__('Province'))
                    ->options(fn (): array => Province::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Province $province): array => [
                            $province->id => $province->getTranslation('name', app()->getLocale()),
                        ])
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
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
