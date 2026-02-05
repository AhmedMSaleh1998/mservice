<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MedicalGuide\Resources\MedicalGuideResource;
use Modules\MedicalGuide\Resources\MedicalGuideDetailsResource;
use Modules\MedicalGuide\Resources\MedicalSpecialtyResource;
use Modules\MedicalGuide\Services\MedicalGuideService;
use Modules\Core\Resources\ProvinceResource;
use Modules\MedicalGuide\Models\MedicalGuide;

class MedicalGuideController extends Controller
{
    public function __construct(
        private readonly MedicalGuideService $medicalGuideService
    )
    {
    }

    public function index(Request $request)
    {
        $keyword = $request->input('search');
        $provinceIds = $this->normalizeIds($request->input('province_ids'));
        $specialties = $this->normalizeIds($request->input('specialty_ids'));
        $limit = (int) $request->input('limit', 100);

        $medicalGuides = $this->medicalGuideService->search(
            $keyword ?? '',
            $specialties,
            $provinceIds,
            $limit
        );

        return response()->json([
            'medical_guides' => MedicalGuideResource::collection($medicalGuides),
            'filters' => [
                'provinces' => ProvinceResource::collection($this->medicalGuideService->getProvinces()),
                'specialties' => MedicalSpecialtyResource::collection($this->medicalGuideService->getSpecialties()),
            ],
        ]);
    }

    public function show(MedicalGuide $medicalGuide)
    {
        $medicalGuide->load([
            'specialty',
            'province',
            'places' => function ($query) {
                $query->active();
            },
        ]);

        return response()->json([
            'medical_guide' => MedicalGuideDetailsResource::make($medicalGuide),
        ]);
    }

    private function normalizeIds($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_numeric($value)) {
            return [(int) $value];
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $value)))));
        }

        return [];
    }

    private function normalizeList($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }
}
