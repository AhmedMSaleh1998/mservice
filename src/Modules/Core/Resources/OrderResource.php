<?php

namespace Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Resources\AdSpaceResource;
use Modules\Certificates\Resources\CertificateResource as CertificateDetailsResource;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Resources\RestUnitResource as RestUnitDetailsResource;
use Modules\Travels\Models\TravelBooking;
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

        if ($orderable instanceof RestUnitBooking) {
            $payload['rest_unit_booking'] = $this->restUnitBookingPayload($orderable);
        }

        if ($orderable instanceof TravelBooking) {
            $payload['travel_booking'] = $this->travelBookingPayload($orderable);
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
            'address' => $membershipRequest->relationLoaded('userAddress')
                ? UserAddressResource::make($membershipRequest->userAddress)->resolve()
                : null,
            'status' => $membershipRequest->status,
            'delivery_status' => $membershipRequest->delivery_status,
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
            'certificate' => $certificateRequest->relationLoaded('certificate')
                ? CertificateDetailsResource::make($certificateRequest->certificate)->resolve()
                : null,
            'delivery_method' => $certificateRequest->delivery_method,
            'delivery_status' => $certificateRequest->delivery_status,
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

    private function restUnitBookingPayload(RestUnitBooking $restUnitBooking): array
    {
        return [
            'id' => $restUnitBooking->id,
            'rest_unit_id' => $restUnitBooking->rest_unit_id,
            'unit_type' => $restUnitBooking->unit_type,
            'start_date' => optional($restUnitBooking->start_date)->toDateString(),
            'end_date' => optional($restUnitBooking->end_date)->toDateString(),
            'status' => $restUnitBooking->status,
            'total_price' => $restUnitBooking->total_price,
            'paid_at' => optional($restUnitBooking->paid_at)->format('Y-m-d H:i:s'),
            'rest_unit' => $restUnitBooking->relationLoaded('restUnit')
                ? RestUnitDetailsResource::make($restUnitBooking->restUnit)->resolve()
                : null,
            'created_at' => optional($restUnitBooking->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function travelBookingPayload(TravelBooking $travelBooking): array
    {
        return [
            'id' => $travelBooking->id,
            'travel_id' => $travelBooking->travel_id,
            'status' => $travelBooking->status,
            'participants_count' => $travelBooking->participants_count,
            'total_amount' => $travelBooking->total_amount,
            'paid_at' => optional($travelBooking->paid_at)->format('Y-m-d H:i:s'),
            'travel' => $travelBooking->relationLoaded('travel')
                ? [
                    'id' => $travelBooking->travel?->id,
                    'title' => $travelBooking->travel?->title,
                    'location' => $travelBooking->travel?->location,
                    'start_date' => optional($travelBooking->travel?->start_date)->toDateString(),
                    'end_date' => optional($travelBooking->travel?->end_date)->toDateString(),
                ]
                : null,
            'created_at' => optional($travelBooking->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}
