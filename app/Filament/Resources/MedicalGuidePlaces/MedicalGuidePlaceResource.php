<?php

namespace App\Filament\Resources\MedicalGuidePlaces;

use App\Filament\Resources\MedicalGuidePlaces\Pages\CreateMedicalGuidePlace;
use App\Filament\Resources\MedicalGuidePlaces\Pages\EditMedicalGuidePlace;
use App\Filament\Resources\MedicalGuidePlaces\Pages\ListMedicalGuidePlaces;
use App\Filament\Resources\MedicalGuidePlaces\Schemas\MedicalGuidePlaceForm;
use App\Filament\Resources\MedicalGuidePlaces\Tables\MedicalGuidePlacesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\MedicalGuide\Models\MedicalGuidePlace;

class MedicalGuidePlaceResource extends Resource
{
    protected static ?string $model = MedicalGuidePlace::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Medical Guides';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Doctor Place');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Doctor Places');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('Medical Guides');
    }

    public static function form(Schema $schema): Schema
    {
        return MedicalGuidePlaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalGuidePlacesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalGuidePlaces::route('/'),
            'create' => CreateMedicalGuidePlace::route('/create'),
            'edit' => EditMedicalGuidePlace::route('/{record}/edit'),
        ];
    }
}
