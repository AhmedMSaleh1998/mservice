<?php

namespace App\Filament\Resources\MedicalGuides;

use App\Filament\Resources\MedicalGuides\Pages\CreateMedicalGuide;
use App\Filament\Resources\MedicalGuides\Pages\EditMedicalGuide;
use App\Filament\Resources\MedicalGuides\Pages\ListMedicalGuides;
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

    public static function form(Schema $schema): Schema
    {
        return MedicalGuideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalGuidesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalGuides::route('/'),
            'create' => CreateMedicalGuide::route('/create'),
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
