<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')
                    ->label('Icon')
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('icon');
                        return $media ? $media->getUrl() : '';
                    })
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl(''),
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('title', app()->getLocale());
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->getStateUsing(function ($record) {
                        $description = $record->getTranslation('description', app()->getLocale());
                        return Str::limit($description, 50);
                    })
                    ->searchable()
                    ->wrap(),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                IconColumn::make('is_active')
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
