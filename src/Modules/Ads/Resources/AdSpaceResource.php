<?php

namespace Modules\Ads\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;

class AdSpaceResource extends CustomResource
{
    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'service' => $this->service->title,
            'max_characters' => $this->resource->max_characters,
            'min_duration_months' => $this->resource->min_duration_months,
            'price_per_month' => $this->resource->price_per_month,
            'is_available' => $this->resource->is_available,
        ];
    }
}
