<?php

namespace Modules\Travels\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class TravelDetailResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'province' => data_get($this->resource, 'province.name'),
            'start_date' => optional($this->resource->start_date)->toDateString(),
            'end_date' => optional($this->resource->end_date)->toDateString(),
            'image_url' => $this->resource->getAttribute('image_url'),
            'itinerary_file_url' => $this->resource->getAttribute('itinerary_file_url'),
            'available_slots' => $this->resource->getAttribute('available_slots') ?? 0,
            'starting_price' => $this->resource->getAttribute('starting_price'),
            'categories' => $this->resource->getAttribute('category_options') ?? [],
            'booking_open' => (bool) ($this->resource->getAttribute('booking_open') ?? false),
        ];
    }
}
