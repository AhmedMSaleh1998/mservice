<?php

namespace App\Filament\Resources\RegistrationRequests\Tables;

use App\Models\RegistrationRequest;
use App\Support\CountryCodeOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RegistrationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('residence_mobile_1')
                    ->label(__('Phone'))
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn ($state, RegistrationRequest $record) => $record->residence_mobile_1_country_code
                        ? trim((CountryCodeOptions::shortLabel($record->residence_mobile_1_country_code) ?? $record->residence_mobile_1_country_code) . ' ' . $state)
                        : $state),
                TextColumn::make('national_id')
                    ->label(__('National ID'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('reg_code')
                    ->label(__('Registration Code'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                IconColumn::make('active')
                    ->label(__('Status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('created_at')
                    ->label(__('Submitted At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('Status'))
                    ->placeholder(__('All requests'))
                    ->trueLabel(__('Active only'))
                    ->falseLabel(__('Inactive only')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('activate')
                        ->label(__('Activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (RegistrationRequest $record) {
                            $record->update(['active' => true]);

                            \Filament\Notifications\Notification::make()
                                ->title(__('Registration Activated'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn (RegistrationRequest $record) => ! $record->active),
                    Action::make('deactivate')
                        ->label(__('Deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (RegistrationRequest $record) {
                            $record->update(['active' => false]);

                            \Filament\Notifications\Notification::make()
                                ->title(__('Registration Deactivated'))
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (RegistrationRequest $record) => $record->active),
                ]),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('created_at', 'desc');
    }
}
