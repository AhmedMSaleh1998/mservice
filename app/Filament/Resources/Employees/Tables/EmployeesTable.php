<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Models\Admin;
use App\Models\Role;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('roles')
                    ->label(__('Roles'))
                    ->getStateUsing(fn (Admin $record): string => $record->roles
                        ->map(fn (Role $role): string => $role->getDisplayName(app()->getLocale()))
                        ->implode(', '))
                    ->placeholder('-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('roles', function (Builder $query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('translated_name->en', 'like', "%{$search}%")
                                ->orWhere('translated_name->ar', 'like', "%{$search}%");
                        });
                    }),
                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('Status'))
                    ->placeholder(__('All employees'))
                    ->trueLabel(__('Active only'))
                    ->falseLabel(__('Inactive only')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
            ])
            ->defaultSort('id', 'desc');
    }
}
