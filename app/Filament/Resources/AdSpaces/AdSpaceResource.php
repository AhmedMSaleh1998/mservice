<?php

namespace App\Filament\Resources\AdSpaces;

use App\Filament\Resources\AdSpaces\Pages\CreateAdSpace;
use App\Filament\Resources\AdSpaces\Pages\EditAdSpace;
use App\Filament\Resources\AdSpaces\Pages\ListAdSpaces;
use App\Filament\Resources\AdSpaces\Schemas\AdSpaceForm;
use App\Filament\Resources\AdSpaces\Tables\AdSpacesTable;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Ads\Models\AdSpace;

class AdSpaceResource extends Resource
{
    protected static ?string $model = AdSpace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return __('Ad Space');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ad Spaces');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('Services');
    }

    public static function form(Schema $schema): Schema
    {
        return AdSpaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdSpacesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('service');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdSpaces::route('/'),
            'create' => CreateAdSpace::route('/create'),
            'edit' => EditAdSpace::route('/{record}/edit'),
        ];
    }
}
