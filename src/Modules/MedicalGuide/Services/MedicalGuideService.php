<?php

namespace Modules\MedicalGuide\Services;

use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\MedicalGuide\Models\MedicalSpecialty;
use Modules\Core\Models\Province;

class MedicalGuideService
{
    protected function baseQuery()
    {
        return MedicalGuide::query();
    }

    public function getMedicalGuides($limit = 100, array $specialties = [], array $provinceIds = [], string $keyword = '')
    {
        return $this->search($keyword, $specialties, $provinceIds, $limit);
    }

    public function search(string $keyword = '', array $specialtyIds = [], array $provinceIds = [], $limit = 10)
    {
        $query = $this->baseQuery()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where('title', 'LIKE', "%{$keyword}%");
            })
            ->when($specialtyIds, function ($query) use ($specialtyIds) {
                $query->whereIn('specialty_id', $specialtyIds);
            })
            ->when($provinceIds, function ($query) use ($provinceIds) {
                $query->whereIn('province_id', $provinceIds);
            })
            ->with([
                'places' => function ($query) {
                    $query->active();
                },
                'specialty',
                'province'
            ])
            ->orderByDesc('is_active')
            ->orderByDesc('is_featured');

        return $query
            ->limit($limit)
            ->get();
    }

    public function getSpecialties()
    {
        return MedicalSpecialty::query()
            ->active()
            ->whereIn('id', $this->getGuideSpecialtyIds())
            ->orderBy('id')
            ->get();
    }

    public function getProvinces()
    {
        return Province::query()
            ->whereIn('id', $this->getGuideProvinceIds())
            ->orderBy('id')
            ->get();
    }

    protected function getGuideSpecialtyIds(): array
    {
        return $this->baseQuery()
            ->whereNotNull('specialty_id')
            ->distinct()
            ->pluck('specialty_id')
            ->filter()
            ->values()
            ->all();
    }

    protected function getGuideProvinceIds(): array
    {
        return $this->baseQuery()
            ->whereNotNull('province_id')
            ->distinct()
            ->pluck('province_id')
            ->filter()
            ->values()
            ->all();
    }
}
