<?php

namespace App\Filament\Resources\Nationalities;

use App\Filament\Resources\Nationalities\Pages\CreateNationality;
use App\Filament\Resources\Nationalities\Pages\EditNationality;
use App\Filament\Resources\Nationalities\Pages\ListNationalities;
use App\Filament\Resources\Nationalities\Schemas\NationalityForm;
use App\Filament\Resources\Nationalities\Tables\NationalitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Models\Nationality;

class NationalityResource extends Resource
{
    protected static ?string $model = Nationality::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static \UnitEnum|string|null $navigationGroup = null;

    protected static ?int $navigationSort = 128;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Nationality');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Nationalities');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return NationalityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NationalitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNationalities::route('/'),
            'create' => CreateNationality::route('/create'),
            'edit' => EditNationality::route('/{record}/edit'),
        ];
    }
}
