<?php

namespace Modules\Ads\Resources;

use Illuminate\Http\Request;
use Modules\Core\Resources\MediaResource;
use Modules\Core\CustomResource;

class AdRequestResource extends CustomResource
{
    public function data(Request $request): array
    {
        $media = $this->resource->getFirstMedia('design_image');

        return [
            'id' => $this->resource->id,
            'status' => $this->resource->status,
            'duration_months' => $this->resource->duration_months,
            'price_per_month' => $this->resource->price_per_month,
            'total_amount' => $this->resource->total_amount,
            'ad_text' => $this->resource->ad_text,
            'design_image_url' => $media?->getUrl(),
            'payment_method' => $this->resource->payment_method,
            'ad_space' => AdSpaceResource::make($this->whenLoaded('adSpace')),
            'created_at' => optional($this->resource->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
