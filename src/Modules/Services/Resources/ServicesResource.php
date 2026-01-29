<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;
use Modules\Services\Resources\ServiceTypeResource;

class ServicesResource extends CustomResource
{

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'key' => $this->resource->key,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'icon' => MediaResource::make($this->resource->getMedia('icon')->last()),
            'service_type' => ServiceTypeResource::make($this->resource->serviceType),
        ];
    }
}
