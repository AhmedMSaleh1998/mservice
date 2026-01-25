<?php

namespace App\Filament\Resources\Courses\Tables;

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

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('Image'))
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('image');
                        return $media ? $media->getUrl() : '';
                    })
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl(''),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('title', app()->getLocale());
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')->label(__('Start Date'))->date(),
                TextColumn::make('end_date')->label(__('End Date'))->date(),
                TextColumn::make('price')->label(__('Price'))->money('SAR'),
                TextColumn::make('type')->label(__('Type'))->searchable(),
                IconColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label(__('Is Featured'))
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
