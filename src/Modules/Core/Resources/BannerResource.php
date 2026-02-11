<?php

namespace Modules\Core\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class BannerResource extends CustomResource
{
    public function data(Request $request): array
    {
        $path = $this->resource->image_path;

        return [
            'id' => $this->resource->id,
            'image' => $path ? asset('storage/' . $path) : null,
            'url' => $this->resource->url,
            'sort_order' => $this->resource->sort_order,
        ];
    }
}
