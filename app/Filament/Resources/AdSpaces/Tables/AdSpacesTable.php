<?php

namespace App\Filament\Resources\AdSpaces\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AdSpacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->label(__('Size'))
                    ->getStateUsing(function ($record) {
                        if (! $record->width || ! $record->height) {
                            return '-';
                        }

                        return $record->width . 'x' . $record->height . ' px';
                    }),
                TextColumn::make('max_characters')
                    ->label(__('Max Characters'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_duration_months')
                    ->label(__('Minimum Months'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_per_month')
                    ->label(__('Price Per Month'))
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('is_available')
                    ->label(__('Is Available'))
                    ->sortable(),
                TextColumn::make('order')
                    ->label(__('Order'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
            ]);
    }
}
