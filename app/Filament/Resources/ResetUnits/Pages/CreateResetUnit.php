<?php

namespace App\Filament\Resources\ResetUnits\Pages;

use App\Filament\Resources\ResetUnits\ResetUnitResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBed;

class CreateResetUnit extends CreateRecord
{
    protected static string $resource = ResetUnitResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record instanceof RestUnit && $record->isBeds()) {
            $total = max((int) ($this->data['beds_total'] ?? 0), 0);

            for ($i = 1; $i <= $total; $i++) {
                $record->beds()->create([
                    'label' => __('Bed :number', ['number' => $i]),
                    'status' => RestUnitBed::STATUS_IN_SERVICE,
                ]);
            }
        }
    }
}
