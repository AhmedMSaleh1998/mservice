<?php

namespace App\Filament\Resources\Travels;

use App\Filament\Resources\Travels\Pages\CreateTravel;
use App\Filament\Resources\Travels\Pages\EditTravel;
use App\Filament\Resources\Travels\Pages\ListTravels;
use App\Filament\Resources\Travels\Schemas\TravelForm;
use App\Filament\Resources\Travels\Tables\TravelsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Travels\Models\Travel;
use UnitEnum;

class TravelResource extends Resource
{
    protected static ?string $model = Travel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static UnitEnum|string|null $navigationGroup = null;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('Travel');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Travels');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): UnitEnum|string|null
    {
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return TravelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTravels::route('/'),
            'create' => CreateTravel::route('/create'),
            'edit' => EditTravel::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
