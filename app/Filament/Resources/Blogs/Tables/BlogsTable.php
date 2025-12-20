<?php

namespace App\Filament\Resources\Blogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('image');
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
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                BooleanColumn::make('is_published')
                    ->label('Published')
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
