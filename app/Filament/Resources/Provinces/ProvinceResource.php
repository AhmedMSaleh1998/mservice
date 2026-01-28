<?php

namespace App\Filament\Resources\Provinces;

use App\Filament\Resources\Provinces\Pages\CreateProvince;
use App\Filament\Resources\Provinces\Pages\EditProvince;
use App\Filament\Resources\Provinces\Pages\ListProvinces;
use App\Filament\Resources\Provinces\Schemas\ProvinceForm;
use App\Filament\Resources\Provinces\Tables\ProvincesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Models\Province;

class ProvinceResource extends Resource
{
    protected static ?string $model = Province::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Province');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Provinces');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('Settings');
    }

    public static function form(Schema $schema): Schema
    {
        return ProvinceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvincesTable::configure($table);
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
            'index' => ListProvinces::route('/'),
            'create' => CreateProvince::route('/create'),
            'edit' => EditProvince::route('/{record}/edit'),
        ];
    }
}
