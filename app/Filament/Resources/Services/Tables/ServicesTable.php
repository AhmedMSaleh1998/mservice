<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Services\Models\ServiceType;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')
                    ->label(__('Icon'))
                    ->getStateUsing(function ($record) {
                        $media = $record->getFirstMedia('icon');
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
                TextColumn::make('serviceType.name')
                    ->label(__('Type'))
                    ->getStateUsing(function ($record) {
                        return $record->serviceType?->getTranslation('name', app()->getLocale());
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $locale = app()->getLocale();

                        return $query->whereHas('serviceType', function (Builder $typeQuery) use ($search, $locale) {
                            $typeQuery->where("name->$locale", 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->getStateUsing(function ($record) {
                        $description = $record->getTranslation('description', app()->getLocale());
                        return Str::limit($description, 50);
                    })
                    ->searchable()
                    ->wrap(),
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),
                // IconColumn::make('is_featured')
                //     ->label(__('Is Featured'))
                //     ->boolean()
                //     ->trueColor('success')
                //     ->falseColor('danger')
                //     ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_type_id')
                    ->label(__('Service Type'))
                    ->options(function () {
                        return ServiceType::query()
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(function (ServiceType $type) {
                                return [$type->id => $type->getTranslation('name', app()->getLocale())];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
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
