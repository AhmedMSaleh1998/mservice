<?php

namespace App\Filament\Resources\ServiceTypes;

use App\Filament\Resources\ServiceTypes\Pages\CreateServiceType;
use App\Filament\Resources\ServiceTypes\Pages\EditServiceType;
use App\Filament\Resources\ServiceTypes\Pages\ListServiceTypes;
use App\Filament\Resources\ServiceTypes\Schemas\ServiceTypeForm;
use App\Filament\Resources\ServiceTypes\Tables\ServiceTypesTable;
use App\Filament\Resources\Services\ServiceResource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Services\Models\ServiceType;

class ServiceTypeResource extends Resource
{
    protected static ?string $model = ServiceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleGroup;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Service Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Service Types');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function getNavigationParentItem(): ?string
    {
        return ServiceResource::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceTypesTable::configure($table);
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
            'index' => ListServiceTypes::route('/'),
            'create' => CreateServiceType::route('/create'),
            'edit' => EditServiceType::route('/{record}/edit'),
        ];
    }
}
