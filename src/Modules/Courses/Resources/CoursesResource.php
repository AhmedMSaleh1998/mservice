<?php

namespace Modules\Courses\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;

class CoursesResource extends CustomResource
{

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'image' => MediaResource::make($this->resource->getMedia('image')->last()),
            'start_date' => $this->resource->start_date->format('Y-m-d'),
            'end_date' => $this->resource->end_date->format('Y-m-d'),
            'type' => __($this->resource->type),
            'price' => (float)$this->resource->price,
        ];
    }
}