<?php

namespace Modules\Services\Builders;

use Illuminate\Database\Eloquent\Builder;
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

    public function whereHasRoomType(int|array|null $roomTypeIds)
    {
        if (blank($roomTypeIds)) {
            return $this;
        }

        $ids = collect(is_array($roomTypeIds) ? $roomTypeIds : [$roomTypeIds])
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $this;
        }

        return $this->whereHas('rooms', function (Builder $query) use ($ids): void {
            $query->whereIn('room_type_id', $ids->all());
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
