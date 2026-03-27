<?php

namespace App\Filament\Resources\Provinces\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Models\Province;

class ProvincesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereNotNull('code')->orderBy('code'))
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('shipping_cost')
                    ->label(__('Shipping Cost'))
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('shipping_cost')
                        ->label(__('Shipping Cost'))
                        ->icon('heroicon-o-truck')
                        ->color('warning')
                        ->schema([
                            TextInput::make('shipping_cost')
                                ->label(__('Shipping Cost'))
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->minValue(0)
                                ->step('0.01')
                                ->prefix('EGP'),
                        ])
                        ->fillForm(fn (Province $record): array => [
                            'shipping_cost' => $record->shipping_cost,
                        ])
                        ->action(function (array $data, Province $record): void {
                            $record->update([
                                'shipping_cost' => $data['shipping_cost'],
                            ]);

                            Notification::make()
                                ->title(__('Shipping cost updated successfully'))
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
            ]);
    }
}
