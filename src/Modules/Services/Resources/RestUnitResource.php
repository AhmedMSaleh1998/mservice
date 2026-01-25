<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\ProvinceResource;

class RestUnitResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'single_rooms' => $this->resource->single_rooms,
            'single_room_price' => $this->resource->single_room_price,
            'double_rooms' => $this->resource->double_rooms,
            'double_room_price' => $this->resource->double_room_price,
            'single_bed' => $this->resource->single_bed,
            'single_bed_price' => $this->resource->single_bed_price,
            'province' => ProvinceResource::make($this->resource->province),
        ];
    }
}