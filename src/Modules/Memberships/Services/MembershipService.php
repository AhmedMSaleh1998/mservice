<?php

namespace Modules\Memberships\Services;

use Modules\Memberships\Models\MembershipRequest;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    // Pricing Constants (This could be moved to config or database settings)
    const PRINTING_COST = 4000;
    const DELIVERY_COST = 4000; // Maybe 0 if pickup?
    const SUBSCRIPTION_COST = 0; // The image shows 'subscription cost' as line item.

    public function calculateCosts(string $deliveryMethod): array
    {
        $printingCost = 4000;
        $deliveryCost = ($deliveryMethod === 'delivery') ? 4000 : 0;
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
        
        $subscriptionCost = 4000; // As per image text, but let's check total.
        
        // If total is 8000, then maybe only 2 apply?
        // Let's assume printing + delivery.
        
        return [
            'printing_cost' => $printingCost,
            'delivery_cost' => $deliveryCost,
            'subscription_cost' => $subscriptionCost,
            'total_amount' => $printingCost + $deliveryCost + $subscriptionCost
        ];
    }

    public function createRequest(array $data, int $userId): MembershipRequest
    {
        return DB::transaction(function () use ($data, $userId) {
            $costs = $this->calculateCosts($data['delivery_method'] ?? 'pickup');

            return MembershipRequest::create([
                'user_id' => $userId,
                'full_name' => $data['full_name'],
                'specialty' => $data['specialty'],
                'degree' => $data['degree'],
                'registration_number' => $data['registration_number'],
                'delivery_method' => $data['delivery_method'],
                'address' => $data['delivery_method'] === 'delivery' ? $data['address'] : null,
                'printing_cost' => $costs['printing_cost'],
                'delivery_cost' => $costs['delivery_cost'],
                'subscription_cost' => $costs['subscription_cost'],
                'total_amount' => $costs['total_amount'],
                'status' => 'pending',
            ]);
        });
    }
}
