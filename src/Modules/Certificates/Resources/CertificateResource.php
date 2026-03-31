<?php

namespace Modules\Certificates\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\MediaResource;

class CertificateResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'price' => $this->resource->price,
            'image' => MediaResource::make($this->resource->getMedia('image')->last()),
        ];
    }
}
