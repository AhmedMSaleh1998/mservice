<?php

namespace Modules\Core\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class ProvinceResource extends CustomResource
{

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}