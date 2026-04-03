<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class RestUnitCheckoutResource extends CustomResource
{
    public function data(Request $request): array
    {
        $coverImageUrl = $this->resource->getAttribute('cover_image_url');

        if ($coverImageUrl === null && method_exists($this->resource, 'getFirstMedia')) {
            $coverImageUrl = $this->resource->getFirstMedia('cover_image')?->getUrl();
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'province' => [
                'id' => $this->resource->province_id,
                'name' => data_get($this->resource, 'province.name'),
            ],
            'cover_image_url' => $coverImageUrl,
        ];
    }
}
