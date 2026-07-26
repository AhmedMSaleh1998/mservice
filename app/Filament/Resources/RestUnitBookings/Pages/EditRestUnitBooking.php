<?php

namespace App\Filament\Resources\RestUnitBookings\Pages;

use App\Filament\Resources\RestUnitBookings\RestUnitBookingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRestUnitBooking extends EditRecord
{
    protected static string $resource = RestUnitBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Preload the multi-select from the booking's current single unit.
        $data['rest_unit_room_ids'] = ! empty($data['rest_unit_room_id']) ? [$data['rest_unit_room_id']] : [];
        $data['rest_unit_bed_ids'] = ! empty($data['rest_unit_bed_id']) ? [$data['rest_unit_bed_id']] : [];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        RestUnitBookingResource::assertUnitsAvailable($this->data, $this->record?->id);

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

    protected function afterSave(): void
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
