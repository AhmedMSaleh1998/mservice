<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\ProvinceResource;

class UserAddressResource extends CustomResource
{

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'district' => $this->resource->district,
            'street' => $this->resource->street,
            'lat' => $this->resource->lat,
            'lng' => $this->resource->lng,
            'phone' => $this->resource->phone,
            'province' => ProvinceResource::make($this->resource->province),
            'unit_number' => $this->resource->unit_number,
            'address_name' => $this->resource->address_name,
        ];
    }
}
