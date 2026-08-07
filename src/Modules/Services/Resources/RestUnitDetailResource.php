<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class RestUnitDetailResource extends CustomResource
{
    public function data(Request $request): array
    {
        $coverImageUrl = $this->resource->getAttribute('cover_image_url');
        $availabilityRequiresDates = (bool) ($this->resource->getAttribute('availability_requires_dates') ?? false);
        $roomTypes = $availabilityRequiresDates
            ? []
            : collect($this->resource->getAttribute('room_options') ?? [])
                ->map(static fn (array $option): array => [
                    'type' => $option['type'] ?? null,
                    'label' => $option['label'] ?? null,
                    'available_count' => $option['available_count'] ?? 0,
                    'price_per_night' => $option['price_per_night'] ?? '0.00',
                    'total_price' => $option['total_price'],
                    'is_available' => (bool) ($option['is_available'] ?? false),
                ])
                ->values()
                ->all();

        if ($coverImageUrl === null && method_exists($this->resource, 'getFirstMedia')) {
            $coverImageUrl = $this->resource->getFirstMedia('cover_image')?->getUrl();
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'description' => $this->resource->description,
            'province' => [
                'id' => $this->resource->province_id,
                'name' => data_get($this->resource, 'province.name'),
            ],
            'cover_image_url' => $coverImageUrl,
            'type' => $this->resource->type,
            'total_places' => $this->resource->getAttribute('total_places') ?? 0,
            'available_places' => $this->resource->getAttribute('available_places'),
            'dates' => $this->resource->getAttribute('dates'),
            'availability_requires_dates' => $availabilityRequiresDates,
            'room_types' => $roomTypes,
        ];
    }
}
