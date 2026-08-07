<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\ProvinceResource;

class RestUnitResource extends CustomResource
{
    public function data(Request $request): array
    {
        $roomOptions = $this->resource->getAttribute('room_options') ?? [];
        $coverImageUrl = $this->resource->getAttribute('cover_image_url');
        $galleryUrls = $this->resource->getAttribute('gallery_urls') ?? [];

        if ($coverImageUrl === null && method_exists($this->resource, 'getFirstMedia')) {
            $coverImageUrl = $this->resource->getFirstMedia('cover_image')?->getUrl();
        }

        if ($galleryUrls === [] && method_exists($this->resource, 'getMedia')) {
            $galleryUrls = $this->resource->getMedia('gallery')->map(fn ($media) => $media->getUrl())->values()->all();
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'description' => $this->resource->description,
            'province_id' => $this->resource->province_id,
            'type' => $this->resource->type,
            'province' => ProvinceResource::make($this->resource->province),
            'cover_image_url' => $coverImageUrl,
            'gallery_urls' => $galleryUrls,
            'room_options' => $roomOptions,
            'room_types' => $roomOptions,
            'total_places' => $this->resource->getAttribute('total_places') ?? 0,
            'available_places' => $this->resource->getAttribute('available_places'),
            'dates' => $this->resource->getAttribute('dates'),
            'availability_requires_dates' => (bool) ($this->resource->getAttribute('availability_requires_dates') ?? false),
        ];
    }
}
