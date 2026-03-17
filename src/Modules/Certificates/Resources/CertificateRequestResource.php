<?php

namespace Modules\Certificates\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Core\Resources\OrderResource;
use Modules\Users\Resources\UserAddressResource;

class CertificateRequestResource extends CustomResource
{

    public function data(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'delivery_method' => $this->resource->delivery_method,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'status' => $this->resource->status,
            'address' => UserAddressResource::make($this->userAddress),
            'costs' => [
                'printing_cost' => $this->resource->printing_cost,
                'delivery_cost' => $this->resource->delivery_cost,
                'subscription_cost' => $this->resource->subscription_cost,
                'total' => $this->resource->total_amount,
            ],
            'order' => OrderResource::make($this->whenLoaded('order')),
        ];
    }
}
