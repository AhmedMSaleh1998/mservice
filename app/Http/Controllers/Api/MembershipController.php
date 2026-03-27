<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMembershipRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Memberships\Services\MembershipService;

class MembershipController extends Controller
{
    public function __construct(
        protected MembershipService $membershipService,
        protected OrderService $orderService,
    ) {
    }

    public function store(StoreMembershipRequest $request): JsonResponse
    {
        $membershipRequest = $this->membershipService->createRequest($request->validated(), $request->user());

        return response()->json([
            'message' => 'Order created successfully.',
            'status' => 200,
            'data' => $this->buildCheckoutPayload($membershipRequest),
        ], 201);
    }

    private function buildCheckoutPayload(MembershipRequest $membershipRequest): array
    {
        $membershipRequest->loadMissing('userAddress.province', 'order');
        $order = $membershipRequest->order;
        $summary = $this->membershipService->buildSummary($membershipRequest);

        return [
            'order' => $this->buildSimpleMembershipOrder($order, $membershipRequest, $summary),
            'payment_methods' => $order && $order->status === 'paid_successfully'
                ? []
                : PaymentMethodResource::collection($this->orderService->availablePaymentMethods()),
        ];
    }

    private function buildSimpleMembershipOrder(?object $order, MembershipRequest $membershipRequest, array $summary): ?array
    {
        if (! $order) {
            return null;
        }

        return [
            'id' => $order->id,
            'status' => $order->status,
            'request' => [
                'id' => $membershipRequest->id,
                'type' => 'membership_request',
                'status' => $membershipRequest->status,
                'full_name' => $membershipRequest->full_name,
                'specialty' => $membershipRequest->specialty,
                'degree' => $membershipRequest->degree,
                'registration_number' => $membershipRequest->registration_number,
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order->amount,
        ];
    }

    private function buildSimpleItems(Collection $items): array
    {
        return $items
            ->map(static fn (array $item): array => [
                'label' => $item['label'] ?? null,
                'amount' => $item['amount'] ?? null,
            ])
            ->values()
            ->all();
    }
}
