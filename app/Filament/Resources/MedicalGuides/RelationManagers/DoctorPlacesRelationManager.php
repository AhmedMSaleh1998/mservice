<?php

namespace App\Filament\Resources\MedicalGuides\RelationManagers;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use App\Filament\Forms\Components\LocationPicker;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DoctorPlacesRelationManager extends RelationManager
{
    protected static string $relationship = 'places';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Places');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableTabs::make('Translations')
                    ->schema([
                        TextInput::make('name')->label(__('Place Name'))->required(),
                        TextInput::make('address')->label(__('Address'))->required(),
                    ])
                    ->columnSpanFull(),
                TagsInput::make('phones')
                    ->label(__('Phones'))
                    ->placeholder(__('Add phone numbers'))
                    ->columnSpanFull()
                    ->required(),
                LocationPicker::make('map')
                    ->latField('lat')
                    ->lngField('lng')
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('lat')->label(__('Latitude'))->numeric()->required(),
                TextInput::make('lng')->label(__('Longitude'))->numeric()->required(),
                Checkbox::make('is_active')->label(__('Is Active')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Place Name'))
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('name', app()->getLocale());
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label(__('Address'))
                    ->getStateUsing(function ($record) {
                        return $record->getTranslation('address', app()->getLocale());
                    })
                    ->searchable(),
                TextColumn::make('phones')
                    ->label(__('Phones'))
                    ->getStateUsing(function ($record) {
                        return implode(' - ', $record->phones ?? []);
                    }),
                IconColumn::make('is_active')
                    ->label(__('Is Active'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
            ])
            ->modelLabel(__('Doctor Place'))
            ->pluralModelLabel(__('Doctor Places'))
            ->filters([
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
