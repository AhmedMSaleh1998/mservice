<?php

namespace App\Filament\Resources\AdSpaces\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Services\Models\Service;
use Filament\Tables\Columns\IconColumn;

class AdSpacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('service_id')
                    ->label(__('Service'))
                    ->formatStateUsing(fn ($state, $record) => $record->service?->getTranslation('title', app()->getLocale())
                        ?: ($record->service?->getTranslation('title', 'en') ?: ($record->service?->key ?? '-'))),
                TextColumn::make('max_characters')
                    ->label(__('Max Characters'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_duration_months')
                    ->label(__('Minimum Months'))
                    ->numeric()
                    ->getStateUsing(function($record)
                    {
                        switch($record->min_duration_months)
                        {
                            case 1:
                                return __('One Month');
                            case 2:
                                return __('Two Months');
                            case 2:
                                return __('Three Months');
                            default:
                                return $record->min_duration_months . ' ' . __('Months');
                        }
                    })
                    ->sortable(),
                TextColumn::make('price_per_month')
                    ->label(__('Price Per Month'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_available')
                    ->label(__('Is Available'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('order')
                    ->label(__('Order'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_id')
                    ->label(__('Service'))
                    ->options(fn () => Service::query()
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Service $service) => [
                            $service->id => $service->getTranslation('title', app()->getLocale())
                                ?: ($service->getTranslation('title', 'en') ?: ($service->key ?? (string) $service->id)),
                        ])
                        ->all()),
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
