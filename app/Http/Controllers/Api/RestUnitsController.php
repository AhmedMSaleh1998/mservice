<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        return response()->json([
            'units' => RestUnitResource::collection($units),
        ]);
    }

    public function subscribe()
    {
    }
}
