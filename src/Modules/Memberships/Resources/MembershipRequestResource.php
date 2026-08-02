<?php

namespace Modules\Memberships\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Resources\OrderResource;
use Modules\Users\Resources\UserAddressResource;

class MembershipRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'specialty' => $this->specialty,
            'degree' => $this->degree,
            'registration_number' => $this->registration_number,
            'address' => UserAddressResource::make($this->userAddress),
            'status' => $this->status,
            'delivery_status' => $this->delivery_status,
            'costs' => [
                'printing' => $this->printing_cost,
                'delivery' => $this->delivery_cost,
                'subscription' => $this->subscription_cost,
                'total' => $this->total_amount,
            ],
            'order' => OrderResource::make($this->whenLoaded('order')),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
