<?php

namespace Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Resources\AdSpaceResource;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Users\Resources\UserAddressResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'provider' => $this->provider,
            'merchant_ref_num' => $this->merchant_ref_num,
            'gateway_reference' => $this->gateway_reference,
            'gateway_status' => $this->gateway_status,
            'checkout_url' => $this->checkout_url,
            'payment_started_at' => optional($this->payment_started_at)->format('Y-m-d H:i:s'),
            'payment_last_synced_at' => optional($this->payment_last_synced_at)->format('Y-m-d H:i:s'),
            'paid_at' => optional($this->paid_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
        ];

        $orderable = $this->whenLoaded('orderable');

        if ($orderable instanceof AdRequest) {
            $payload['ad_request'] = $this->adRequestPayload($orderable);
        }

        if ($orderable instanceof MembershipRequest) {
            $payload['membership_request'] = $this->membershipRequestPayload($orderable);
        }

        if ($orderable instanceof CertificateRequest) {
            $payload['certificate_request'] = $this->certificateRequestPayload($orderable);
        }

        return $payload;
    }

    private function adRequestPayload(AdRequest $adRequest): array
    {
        $media = $adRequest->getFirstMedia('design_image');

        return [
            'id' => $adRequest->id,
            'status' => $adRequest->status,
            'duration_months' => $adRequest->duration_months,
            'price_per_month' => $adRequest->price_per_month,
            'total_amount' => $adRequest->total_amount,
            'ad_text' => $adRequest->ad_text,
            'design_image_url' => $media?->getUrl(),
            'payment_method' => $this->payment_method,
            'ad_space' => $adRequest->relationLoaded('adSpace')
                ? AdSpaceResource::make($adRequest->adSpace)->resolve()
                : null,
            'created_at' => optional($adRequest->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function membershipRequestPayload(MembershipRequest $membershipRequest): array
    {
        return [
            'id' => $membershipRequest->id,
            'full_name' => $membershipRequest->full_name,
            'specialty' => $membershipRequest->specialty,
            'degree' => $membershipRequest->degree,
            'registration_number' => $membershipRequest->registration_number,
            'delivery_method' => $membershipRequest->delivery_method,
            'address' => $membershipRequest->relationLoaded('userAddress')
                ? UserAddressResource::make($membershipRequest->userAddress)->resolve()
                : null,
            'status' => $membershipRequest->status,
            'costs' => [
                'printing' => $membershipRequest->printing_cost,
                'delivery' => $membershipRequest->delivery_cost,
                'subscription' => $membershipRequest->subscription_cost,
                'total' => $membershipRequest->total_amount,
            ],
            'created_at' => optional($membershipRequest->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function certificateRequestPayload(CertificateRequest $certificateRequest): array
    {
        return [
            'id' => $certificateRequest->id,
            'delivery_method' => $certificateRequest->delivery_method,
            'phone' => $certificateRequest->phone,
            'email' => $certificateRequest->email,
            'status' => $certificateRequest->status,
            'address' => $certificateRequest->relationLoaded('userAddress')
                ? UserAddressResource::make($certificateRequest->userAddress)->resolve()
                : null,
            'costs' => [
                'printing_cost' => $certificateRequest->printing_cost,
                'delivery_cost' => $certificateRequest->delivery_cost,
                'subscription_cost' => $certificateRequest->subscription_cost,
                'total' => $certificateRequest->total_amount,
            ],
            'created_at' => optional($certificateRequest->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
