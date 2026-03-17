<?php

namespace Modules\Ads\Resources;

use Illuminate\Http\Request;
use Modules\Core\Resources\MediaResource;
use Modules\Core\CustomResource;
use Modules\Core\Resources\OrderResource;

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
            'payment_method' => data_get($this->resource, 'order.payment_method'),
            'order' => OrderResource::make($this->whenLoaded('order')),
            'ad_space' => AdSpaceResource::make($this->whenLoaded('adSpace')),
            'starts_at' => optional($this->resource->starts_at)->format('Y-m-d H:i:s'),
            'ends_at' => optional($this->resource->ends_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($this->resource->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
