<?php

namespace App\Filament\Resources\DeliveryRequests;

use App\Filament\Resources\DeliveryRequests\Pages\ListDeliveryRequests;
use App\Filament\Resources\DeliveryRequests\Pages\ViewDeliveryRequest;
use App\Filament\Resources\DeliveryRequests\Tables\DeliveryRequestsTable;
use App\Filament\Support\OrderInfolist;
use App\Support\OrderAdminSupport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Order;

class DeliveryRequestResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::QueueList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 43;

    public static function getModelLabel(): string
    {
        return __('Delivery Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Delivery Requests');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        return OrderAdminSupport::applyDeliveryScope(
            OrderAdminSupport::applyEagerLoading(parent::getEloquentQuery())
        );
    }

    public static function table(Table $table): Table
    {
        return DeliveryRequestsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveryRequests::route('/'),
            'view' => ViewDeliveryRequest::route('/{record}'),
        ];
    }
}
