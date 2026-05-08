<?php

namespace Modules\MedicalGuide\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class DoctorMedicalGuideResource extends CustomResource
{
    public function data(Request $request): array
    {
        $places = $this->resource->relationLoaded('places') ? $this->resource->places : collect();

        $specialty = $this->resource->specialty?->getTranslation('name', app()->getLocale())
            ?? $this->resource->description;

        $provinceFromRecord = $this->resource->relationLoaded('province') ? $this->resource->province : null;

        return [
            'id' => $this->resource->id,
            'reg_number' => $this->resource->reg_number,
            'name' => $this->resource->title,
            'specialty_id' => $this->resource->specialty_id,
            'specialty' => $specialty,
            'image' => $this->resource->getFirstMediaUrl('image') ?: null,
            'province_id' => $this->resource->province_id,
            'province' => $provinceFromRecord?->getTranslation('name', app()->getLocale()),
            'is_active' => (bool) $this->resource->is_active,
            'clinics' => $places->map(fn ($place) => [
                'id' => $place->id,
                'name' => $place->name,
                'address' => $place->address,
                'phones' => array_values($place->phones ?? []),
                'lat' => $place->lat !== null ? (float) $place->lat : null,
                'lng' => $place->lng !== null ? (float) $place->lng : null,
                'is_active' => (bool) $place->is_active,
            ])->values(),
        ];
    }
}
