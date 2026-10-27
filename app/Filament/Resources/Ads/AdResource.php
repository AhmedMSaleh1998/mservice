<?php

namespace App\Filament\Resources\Ads;

use App\Filament\Resources\Ads\Pages\ListAds;
use App\Filament\Resources\Ads\Tables\AdsTable;
use App\Filament\Resources\AdSpaces\AdSpaceResource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Ads\Models\AdRequest;

/**
 * Approved ad requests only: the ads that actually went live, running or ended.
 * Viewing and editing a single ad reuses the ad request pages.
 */
class AdResource extends Resource
{
    protected static ?string $model = AdRequest::class;

    protected static ?string $slug = 'ads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Photo;

    protected static ?int $navigationSort = 32;

    public static function getModelLabel(): string
    {
        return __('Ad');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ads');
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
        return AdSpaceResource::getNavigationLabel();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->published()
            ->with(['user', 'adSpace.service', 'media']);
    }

    public static function table(Table $table): Table
    {
        return AdsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAds::route('/'),
        ];
    }
}
