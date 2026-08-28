<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Support\OrderAdminSupport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $relatedResource = TransactionResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Transactions');
    }

    public function table(Table $table): Table
    {
        // The transactions screen opens filtered to paid orders; on the member's
        // page the full history matters, so drop the preset (the filter stays usable).
        $table->getFilter('status')?->default(null);

        return $table->modifyQueryUsing(fn (Builder $query): Builder => OrderAdminSupport::applyEagerLoading($query));
    }
}
