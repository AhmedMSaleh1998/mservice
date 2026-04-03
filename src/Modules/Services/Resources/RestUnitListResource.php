<?php

namespace Modules\Services\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class RestUnitListResource extends CustomResource
{
    protected static function newCollection($resource): RestUnitListCollection
    {
        return new RestUnitListCollection($resource);
    }

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'province' => [
                'id' => $this->resource->province_id,
                'name' => data_get($this->resource, 'province.name'),
            ],
            'available_places' => $this->resource->getAttribute('available_places')
                ?? ($this->resource->getAttribute('total_places') ?? 0),
        ];
    }
}
