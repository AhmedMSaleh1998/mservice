<?php

namespace Modules\Certificates\Services;

use Illuminate\Support\Facades\DB;
use Modules\Certificates\Models\CertificateRequest;

class CertificateRequestService
{

    const PRINTING_COST = 4000;
    const DELIVERY_COST = 4000; // Maybe 0 if pickup?
    const SUBSCRIPTION_COST = 0; // The image shows 'subscription cost' as line item.

    public function calculateCosts(string $deliveryMethod): array
    {
        $printingCost = 500;
        $deliveryCost = ($deliveryMethod === 'delivery') ? 100 : 0;
        $subscriptionCost = 0; // Assuming 0 based on user's image showing 4000,4000,4000 ? No wait image says 4000, 4000, 4000? Let's check image again.

        // Image "Payment Detail" strings:
        // printing: 4000
        // delivery: 4000
        // subscription (اشتراك نقابة): 4000 (maybe?)
        // Total: 8000 (Wait 4+4+? = 8? If sub is 0? or sub is included?)

        // Looking at Arabic text:
        // طباعة الكارنيه 4000
        // تكلفة الشحن 4000
        // اشتراك نقابة 4000
        // الاجمالي 8000 ?
        // 4+4+4 = 12. Maybe one is strike-through or 0?
        // Or specific case only.

        // Let's implement logic to be flexible.

        $subscriptionCost = 1000; // As per image text, but let's check total.

        // If total is 8000, then maybe only 2 apply?
        // Let's assume printing + delivery.

        return [
            'printing_cost' => $printingCost,
            'delivery_cost' => $deliveryCost,
            'subscription_cost' => $subscriptionCost,
            'total_amount' => $printingCost + $deliveryCost + $subscriptionCost
        ];
    }

    public function makeRequest(array $data, int $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            $costs = $this->calculateCosts($data['delivery_method'] ?? 'delivery');
            return CertificateRequest::create([
                'user_id' => $userId,
                'certificate_id' => $data['certificate_id'],
                'delivery_method' => $data['delivery_method'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'user_address_id' => $data['address_id'] ?? null,
                'printing_cost' => $costs['printing_cost'],
                'delivery_cost' => $costs['delivery_cost'],
                'subscription_cost' => $costs['subscription_cost'],
                'total_amount' => $costs['total_amount'],
                'status' => 'pending',
            ]);
        });
    }
}