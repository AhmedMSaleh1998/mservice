<?php

namespace App\Filament\Resources\MedicalGuides;

use App\Filament\Resources\MedicalGuides\Pages\CreateMedicalGuide;
use App\Filament\Resources\MedicalGuides\Pages\EditMedicalGuide;
use App\Filament\Resources\MedicalGuides\Pages\ListMedicalGuides;
use App\Filament\Resources\MedicalGuides\Pages\ViewMedicalGuide;
use App\Filament\Resources\MedicalGuides\RelationManagers\DoctorPlacesRelationManager;
use App\Filament\Resources\MedicalGuides\Schemas\MedicalGuideInfolist;
use App\Filament\Resources\MedicalGuides\Schemas\MedicalGuideForm;
use App\Filament\Resources\MedicalGuides\Tables\MedicalGuidesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\MedicalGuide\Models\MedicalGuide;
use UnitEnum;

class MedicalGuideResource extends Resource
{
    protected static ?string $model = MedicalGuide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
    
    protected static UnitEnum|string|null $navigationGroup = 'Medical Guides';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('Medical Guide');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Medical Guides');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): UnitEnum|string|null
    {
        return __('Medical Guides');
    }

    public static function form(Schema $schema): Schema
    {
        return MedicalGuideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalGuidesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MedicalGuideInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            DoctorPlacesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalGuides::route('/'),
            'create' => CreateMedicalGuide::route('/create'),
            'view' => ViewMedicalGuide::route('/{record}'),
            'edit' => EditMedicalGuide::route('/{record}/edit'),
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
