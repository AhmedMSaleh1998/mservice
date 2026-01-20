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
            'type' => $this->resource->type,
            'phone' => $this->resource->phone,
            'province' => ProvinceResource::make($this->resource->province),
        ];
    }
}