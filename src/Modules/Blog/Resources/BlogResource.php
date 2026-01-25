<?php

namespace Modules\Blog\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;

class BlogResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'slug' => $this->resource->slug,
            'image' => MediaResource::make($this->resource->getMedia('image')->last()),
            'description' => $this->resource->description,
            'content' => $this->when($this->isDetailedView(), $this->resource->content),
            'published_at' => $this->resource->created_at->format('Y-m-d H:i:s'),
        ];
    }

    protected function isDetailedView(): bool
    {
        return request()->route()->getName() === 'api.blogs.show';
    }
}
