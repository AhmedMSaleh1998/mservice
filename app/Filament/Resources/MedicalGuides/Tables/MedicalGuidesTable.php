<?php

namespace App\Filament\Resources\MedicalGuides\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MedicalGuidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Doctor Name'))
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('title', app()->getLocale());
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('Specialty'))
                    ->getStateUsing(function ($record) {
                        $specialty = $record->specialty?->getTranslation('name', app()->getLocale());
                        return $specialty ?: $record->getTranslation('description', app()->getLocale());
                    })
                    ->searchable(),
                TextColumn::make('province_id')
                    ->label(__('Province'))
                    ->getStateUsing(function ($record) {
                        return $record->province?->getTranslation('name', app()->getLocale());
                    })
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->sortable(),
            ])
            ->filters([
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
                ]),
            ]);
    }
}
