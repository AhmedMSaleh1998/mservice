<?php

namespace Modules\MedicalGuide\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class MedicalSpecialtyResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
