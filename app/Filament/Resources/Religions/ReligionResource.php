<?php

namespace App\Filament\Resources\Religions;

use App\Filament\Resources\Religions\Pages\CreateReligion;
use App\Filament\Resources\Religions\Pages\EditReligion;
use App\Filament\Resources\Religions\Pages\ListReligions;
use App\Filament\Resources\Religions\Schemas\ReligionForm;
use App\Filament\Resources\Religions\Tables\ReligionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Models\Religion;

class ReligionResource extends Resource
{
    protected static ?string $model = Religion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static \UnitEnum|string|null $navigationGroup = null;

    protected static ?int $navigationSort = 127;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Religion');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Religions');
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
        return ReligionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReligionsTable::configure($table);
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
            'index' => ListReligions::route('/'),
            'create' => CreateReligion::route('/create'),
            'edit' => EditReligion::route('/{record}/edit'),
        ];
    }
}
