<?php

namespace Modules\Pages\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class PageResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'content' => $this->resource->content,
            'updated_at' => $this->resource->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
