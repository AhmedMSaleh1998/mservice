<?php

namespace Modules\Travels\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class TravelListResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'province' => data_get($this->resource, 'province.name'),
            'start_date' => optional($this->resource->start_date)->toDateString(),
            'end_date' => optional($this->resource->end_date)->toDateString(),
            'image_url' => $this->resource->getAttribute('image_url'),
            'available_slots' => $this->resource->getAttribute('available_slots') ?? 0,
            'starting_price' => $this->resource->getAttribute('starting_price'),
        ];
    }
}
