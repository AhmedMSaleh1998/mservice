<?php

namespace App\Filament\Resources\RestUnitBookings\Pages;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRestUnitBooking extends CreateRecord
{
    protected static string $resource = RestUnitBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        RestUnitBookingResource::assertUnitsAvailable($this->data);

        $data = RestUnitBookingResource::applyBookingDefaults($data);

        $data['total_price'] = RestUnitBookingResource::calculateAmount(
            $this->data['start_date'] ?? null,
            $this->data['end_date'] ?? null,
            $this->data['rest_unit_id'] ?? null,
            (array) ($this->data['rest_unit_room_ids'] ?? []),
            (array) ($this->data['rest_unit_bed_ids'] ?? []),
        );

        $selected = RestUnitBookingResource::resolveSelectedUnits($this->data);
        if ($selected['col'] && $selected['ids'] !== []) {
            $data[$selected['col']] = $selected['ids'][0];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $selected = RestUnitBookingResource::resolveSelectedUnits($this->data);

        if ($selected['col'] && count($selected['ids']) > 1) {
            RestUnitBookingResource::replicateForExtraUnits(
                $this->record,
                $selected['col'],
                array_slice($selected['ids'], 1),
            );
        }
    }
}
