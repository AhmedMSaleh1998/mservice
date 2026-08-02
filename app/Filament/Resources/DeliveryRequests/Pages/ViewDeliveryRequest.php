<?php

namespace App\Filament\Resources\DeliveryRequests\Pages;

use App\Filament\Resources\DeliveryRequests\DeliveryRequestResource;
use App\Filament\Resources\DeliveryRequests\Tables\DeliveryRequestsTable;
use App\Support\OrderAdminSupport;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class ViewDeliveryRequest extends ViewRecord
{
    protected static string $resource = DeliveryRequestResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            DeliveryRequestsTable::updateDeliveryStatusAction(),
        ];
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Model $record */
        $record = parent::resolveRecord($key);

        OrderAdminSupport::loadRelations($record);

        return $record;
    }
}
