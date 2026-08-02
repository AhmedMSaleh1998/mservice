<?php

namespace App\Filament\Resources\MedicalUniversities;

use App\Filament\Resources\MedicalUniversities\Pages\CreateMedicalUniversity;
use App\Filament\Resources\MedicalUniversities\Pages\EditMedicalUniversity;
use App\Filament\Resources\MedicalUniversities\Pages\ListMedicalUniversities;
use App\Filament\Resources\MedicalUniversities\Schemas\MedicalUniversityForm;
use App\Filament\Resources\MedicalUniversities\Tables\MedicalUniversitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Models\MedicalUniversity;

class MedicalUniversityResource extends Resource
{
    protected static ?string $model = MedicalUniversity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static \UnitEnum|string|null $navigationGroup = null;

    protected static ?int $navigationSort = 129;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Medical University');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Medical Universities');
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
        return MedicalUniversityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalUniversitiesTable::configure($table);
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
            'index' => ListMedicalUniversities::route('/'),
            'create' => CreateMedicalUniversity::route('/create'),
            'edit' => EditMedicalUniversity::route('/{record}/edit'),
        ];
    }
}
