<?php

namespace Modules\Services\Builders;

use Illuminate\Database\Eloquent\Builder;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;

class RestUnitQueryBuilder extends Builder
{
    public function whereProvince(int|array|null $provinceIds)
    {
        if (blank($provinceIds)) {
            return $this;
        }

        $provinceIds = collect(is_array($provinceIds) ? $provinceIds : [$provinceIds])
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();

        return $provinceIds->count() === 1
            ? $this->where('province_id', $provinceIds->first())
            : $this->whereIn('province_id', $provinceIds->all());
    }

    public function whereHasRoomType(string|array|null $roomTypes)
    {
        if (blank($roomTypes)) {
            return $this;
        }

        $roomTypes = collect(is_array($roomTypes) ? $roomTypes : [$roomTypes])
            ->map(fn (mixed $value): ?string => RestUnit::normalizeUnitType((string) $value))
            ->filter()
            ->unique()
            ->values();

        return $this->where(function (Builder $query) use ($roomTypes): void {
            foreach ($roomTypes as $type) {
                $column = RestUnit::inventoryColumnForType($type);

                if ($column) {
                    $query->orWhere($column, '>', 0);
                }
            }
        });
    }

    public function availableBetween($startDate, $endDate)
    {
        return $this->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereDoesntHave('bookings', function ($q) use ($startDate, $endDate) {
                $q->where(function ($subQ) use ($startDate, $endDate) {
                    $subQ->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate)
                        ->whereNotIn('status', [
                            RestUnitBooking::STATUS_CANCELLED,
                            RestUnitBooking::STATUS_PAYMENT_EXPIRED,
                        ]);
                });
            });
        });
    }
}
