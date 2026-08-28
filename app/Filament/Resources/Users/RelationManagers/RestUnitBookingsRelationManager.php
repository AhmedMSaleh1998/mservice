<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RestUnitBookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'restUnitBookings';

    protected static ?string $relatedResource = RestUnitBookingResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Rest Unit Bookings');
    }

    public function table(Table $table): Table
    {
        // Show the member's full booking history, not just the paid ones.
        $table->getFilter('status')?->default(null);

        return $table;
    }
}
