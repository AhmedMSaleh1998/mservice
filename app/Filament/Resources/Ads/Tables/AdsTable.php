<?php

namespace App\Filament\Resources\Ads\Tables;

use App\Filament\Resources\AdRequests\AdRequestResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Ads\Enums\AdDisplayStateEnum;
use Modules\Ads\Models\AdRequest;

class AdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable(),
                SpatieMediaLibraryImageColumn::make('design_image')
                    ->label(__('Design Image'))
                    ->collection('design_image')
                    ->imageHeight(56)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->description(fn (AdRequest $record): ?string => $record->user?->reg_number)
                    ->searchable(['name', 'reg_number'])
                    ->sortable(),
                TextColumn::make('adSpace.name')
                    ->label(__('Ad Space'))
                    ->getStateUsing(fn (AdRequest $record): string => AdRequestResource::getAdSpaceLabel($record->adSpace)),
                TextColumn::make('ad_text')
                    ->label(__('Ad Text'))
                    ->limit(40)
                    ->tooltip(fn (AdRequest $record): ?string => $record->ad_text)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label(__('Starts At'))
                    ->date('Y-m-d')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('Ends At'))
                    ->date('Y-m-d')
                    ->description(fn (AdRequest $record): ?string => AdRequestResource::daysLeftLabel($record))
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('display_state')
                    ->label(__('Ad State'))
                    ->badge()
                    ->getStateUsing(fn (AdRequest $record): string => $record->displayState()->label())
                    ->color(fn (AdRequest $record): string => $record->displayState()->color()),
                ToggleColumn::make('is_visible')
                    ->label(__('Visible in app'))
                    ->onColor('success')
                    // Only a running ad can be shown; ended ones are off the app anyway.
                    ->disabled(fn (AdRequest $record): bool => ! $record->isRunning() || ! AdRequestResource::canEdit($record))
                    ->afterStateUpdated(fn (AdRequest $record) => AdRequestResource::notifyVisibilityChanged($record)),
            ])
            ->filters([
                SelectFilter::make('display_state')
                    ->label(__('Ad State'))
                    ->options(AdDisplayStateEnum::options())
                    ->query(fn (Builder $query, array $data): Builder => match (AdDisplayStateEnum::tryFrom((string) ($data['value'] ?? ''))) {
                        AdDisplayStateEnum::RUNNING => $query->running(),
                        AdDisplayStateEnum::ENDED => $query->ended(),
                        default => $query,
                    }),
                TernaryFilter::make('is_visible')
                    ->label(__('Visible in app')),
            ])
            ->recordUrl(fn (AdRequest $record): string => AdRequestResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('View'))
                        ->icon('heroicon-o-eye')
                        ->url(fn (AdRequest $record): string => AdRequestResource::getUrl('view', ['record' => $record])),
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (AdRequest $record): string => AdRequestResource::getUrl('edit', ['record' => $record]))
                        ->visible(fn (AdRequest $record): bool => AdRequestResource::canEdit($record)),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
