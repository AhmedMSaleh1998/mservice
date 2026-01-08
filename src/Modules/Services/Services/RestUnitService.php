<?php

namespace Modules\Services\Services;

use Modules\Services\Models\RestUnit;

class RestUnitService
{
    private function baseQuery()
    {
        return RestUnit::query()->where('is_active', true);
    }

    public function getList(int $limit = 100, array $filters = [])
    {
        return $this->baseQuery()
            ->whereProvince($filters['province_id'] ?? null)
            ->whereHasRoomType($filters['room_type'] ?? null)
            ->availableBetween($filters['from_date'] ?? null, $filters['to_date'] ?? null)
            ->paginate($limit);
    }
}