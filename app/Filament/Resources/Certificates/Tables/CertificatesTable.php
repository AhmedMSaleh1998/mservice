<?php

namespace App\Filament\Resources\Certificates\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('Image'))
                    ->collection('image')
                    ->circular()
                    ->size(50),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('name', app()->getLocale());
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('pand_id')
                    ->label(__('Oracle certificate number'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                ToggleColumn::make('is_active')
                    ->label(__('Is Active')),
            ])
            ->filters([
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ]);
    }
}
