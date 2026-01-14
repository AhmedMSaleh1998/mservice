<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;

class ServicesResource extends CustomResource
{

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'icon' => MediaResource::make($this->resource->getMedia('icon')->last()),
        ];
    }
}