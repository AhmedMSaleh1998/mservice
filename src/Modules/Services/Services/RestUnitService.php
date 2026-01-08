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

    public function book(array $data)
    {
        $unit = RestUnit::findOrFail($data['rest_unit_id']);
        $unitType = $data['unit_type']; // 'single_rooms', 'double_rooms', 'single_bed'

        // Check availability
        $isAvailable = RestUnit::where('id', $unit->id)
            ->availableBetween($data['start_date'], $data['end_date'])
            ->exists();

        if (!$isAvailable) {
            throw new \Exception("Rest Unit is not available for the selected dates.");
        }

        // Calculate Price
        $pricePerNight = match ($unitType) {
            'single_rooms' => $unit->single_room_price,
            'double_rooms' => $unit->double_room_price,
            'single_bed' => $unit->single_bed_price,
            default => 0,
        };

        $start = \Carbon\Carbon::parse($data['start_date']);
        $end = \Carbon\Carbon::parse($data['end_date']);
        $nights = $start->diffInDays($end) ?: 1; // Minimum 1 night if same day logic applies? Usually hotels are nights.
        $totalPrice = $pricePerNight * $nights;

        return $unit->bookings()->create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'unit_type' => $unitType,
            'total_price' => $totalPrice,
            'status' => 'pending',
        ]);
    }
}