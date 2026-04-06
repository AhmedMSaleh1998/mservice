<?php

namespace App\Filament\Resources\ResetUnits;

use App\Filament\Resources\ResetUnits\Pages\CreateResetUnit;
use App\Filament\Resources\ResetUnits\Pages\EditResetUnit;
use App\Filament\Resources\ResetUnits\Pages\ListResetUnits;
use App\Filament\Resources\ResetUnits\Pages\ViewResetUnit;
use App\Filament\Resources\ResetUnits\Schemas\ResetUnitForm;
use App\Filament\Resources\ResetUnits\Schemas\ResetUnitInfolist;
use App\Filament\Resources\ResetUnits\Tables\ResetUnitsTable;
use BackedEnum;
use Modules\Services\Models\RestUnit;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ResetUnitResource extends Resource
{
    protected static ?string $model = RestUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::HomeModern;

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Rest Unit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Rest Units');
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
        return ResetUnitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ResetUnitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResetUnitsTable::configure($table);
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
            'index' => ListResetUnits::route('/'),
            'create' => CreateResetUnit::route('/create'),
            'view' => ViewResetUnit::route('/{record}'),
            'edit' => EditResetUnit::route('/{record}/edit'),
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
