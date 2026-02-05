<?php

namespace Modules\MedicalGuide\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class MedicalGuideDetailsResource extends CustomResource
{
    public function data(Request $request): array
    {
        $places = $this->resource->relationLoaded('places') ? $this->resource->places : collect();
        $primaryPlace = $places->first();
        $primaryPhone = $primaryPlace?->phones[0] ?? null;

        $specialty = $this->resource->specialty?->getTranslation('name', app()->getLocale())
            ?? $this->resource->description;

        $provinceFromRecord = $this->resource->relationLoaded('province') ? $this->resource->province : null;
        $provinceId = $this->resource->province_id;
        $provinceName = $provinceFromRecord?->getTranslation('name', app()->getLocale());

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->title,
            'specialty_id' => $this->resource->specialty_id,
            'specialty' => $specialty,
            'image' => $this->resource->getFirstMediaUrl('image') ?: null,
            'phone' => $primaryPhone,
            'province_id' => $provinceId,
            'province' => $provinceName,
            'places' => MedicalGuidePlaceResource::collection($places),
        ];
    }
}
