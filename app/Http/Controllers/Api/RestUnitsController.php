<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Services\Models\RestUnit;
use Modules\Services\Resources\RestUnitResource;
use Modules\Services\Services\RestUnitService;

class RestUnitsController extends Controller
{
    public function __construct(
        private readonly RestUnitService $restUnitService
    )
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'province_id' => 'nullable|integer|exists:provinces,id',
            'room_type' => 'nullable|string|in:single_rooms,double_rooms,single_bed',
            'from_date' => 'nullable|date|required_with:to_date',
            'to_date' => 'nullable|date|required_with:from_date|after_or_equal:from_date',
        ]);

        $units = $this->restUnitService->getList(100, $validated);
        return RestUnitResource::collection($units);
    }

    public function show($id)
    {
        $restUnit = RestUnit::findOrFail($id);
        return RestUnitResource::make($restUnit);
    }

    public function booking(Request $request)
    {
        $validated = $request->validate([
            'rest_unit_id' => 'required|exists:rest_units,id',
            'unit_type' => 'required|string|in:single_rooms,double_rooms,single_bed',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        try {
            $booking = $this->restUnitService->book([
                'rest_unit_id' => $validated['rest_unit_id'],
                'user_id' => auth('sanctum')->id(),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'unit_type' => $validated['unit_type'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking request submitted successfully.',
                'booking_id' => $booking->id,
                'total_price' => $booking->total_price,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422); // Unprocessable Entity
        }
    }
}
