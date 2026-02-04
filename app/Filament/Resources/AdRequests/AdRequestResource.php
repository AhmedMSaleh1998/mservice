<?php

namespace App\Filament\Resources\AdRequests;

use App\Filament\Resources\AdRequests\Pages\EditAdRequest;
use App\Filament\Resources\AdRequests\Pages\ListAdRequests;
use App\Filament\Resources\AdRequests\Pages\ViewAdRequest;
use App\Filament\Resources\AdRequests\Schemas\AdRequestForm;
use App\Filament\Resources\AdRequests\Schemas\AdRequestInfolist;
use App\Filament\Resources\AdRequests\Tables\AdRequestsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Ads\Models\AdRequest;

class AdRequestResource extends Resource
{
    protected static ?string $model = AdRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('Ad Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ad Requests');
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
        return AdRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdRequestsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdRequestInfolist::configure($schema);
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
            'index' => ListAdRequests::route('/'),
            'view' => ViewAdRequest::route('/{record}'),
            'edit' => EditAdRequest::route('/{record}/edit'),
        ];
    }
}
