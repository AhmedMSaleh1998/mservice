<?php

namespace App\Filament\Resources\MedicalGuidePlaces\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MedicalGuidePlacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('doctor.title')
                    ->label(__('Doctor'))
                    ->getStateUsing(function ($record) {
                        return $record->doctor?->getTranslation('title', app()->getLocale());
                    })
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('Place Name'))
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('name', app()->getLocale());
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phones')
                    ->label(__('Phones'))
                    ->getStateUsing(function ($record) {
                        $phones = $record->phones ?? [];
                        return implode(' - ', $phones);
                    }),
                IconColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
