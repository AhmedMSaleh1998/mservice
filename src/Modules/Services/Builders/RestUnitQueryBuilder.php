<?php

namespace Modules\Services\Builders;

use Illuminate\Database\Eloquent\Builder;

class RestUnitQueryBuilder extends Builder
{

    public function whereProvince($provinceId)
    {
        return $this->when($provinceId, function ($query, $provinceId) {
            $query->where('province_id', $provinceId);
        });
    }

    public function whereHasRoomType($roomType)
    {
        return $this->when($roomType, function ($query, $roomType) {
            // Mapping frontend room types to database columns
            $column = match ($roomType) {
                'single_rooms' => 'single_rooms',
                'double_rooms' => 'double_rooms',
                'single_bed' => 'single_bed', // Assuming 'single_bed' is the column name for single beds in shared rooms? Or just beds? Matches schema.
                default => null,
            };

            if ($column) {
                $query->where($column, '>', 0);
            }
        });
    }

    public function availableBetween($startDate, $endDate)
    {
        return $this->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereDoesntHave('bookings', function ($q) use ($startDate, $endDate) {
                $q->where(function ($subQ) use ($startDate, $endDate) {
                   // Check for overlap
                   // (StartA <= EndB) and (EndA >= StartB)
                   $subQ->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate)
                        ->where('status', 'active'); // Only consider active bookings
                });
            });
        });
    }
}