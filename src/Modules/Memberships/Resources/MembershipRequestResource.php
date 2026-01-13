<?php

namespace Modules\Memberships\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'delivery_method' => $this->delivery_method,
            'address' => $this->address,
            'status' => $this->status,
            'costs' => [
                'printing' => $this->printing_cost,
                'delivery' => $this->delivery_cost,
                'subscription' => $this->subscription_cost,
                'total' => $this->total_amount,
            ],
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
