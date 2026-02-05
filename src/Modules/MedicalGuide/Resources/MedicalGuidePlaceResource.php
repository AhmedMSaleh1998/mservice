<?php

namespace Modules\MedicalGuide\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class MedicalGuidePlaceResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'phones' => array_values($this->resource->phones ?? []),
            'lat' => $this->resource->lat !== null ? (float) $this->resource->lat : null,
            'lng' => $this->resource->lng !== null ? (float) $this->resource->lng : null,
        ];
    }
}
