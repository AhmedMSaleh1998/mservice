<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAdRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Resources\AdRequestResource;
use Modules\Ads\Services\AdRequestService;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;

class AdRequestsController extends Controller
{
    public function __construct(
        private readonly AdRequestService $adRequestService,
        private readonly OrderService $orderService,
    ) {
    }

    public function store(StoreAdRequest $request): JsonResponse
    {
        $adRequest = $this->adRequestService->create($request->validated(), auth()->id());

        return response()->json([
            'message' => 'Order created successfully.',
            'status' => 200,
            'data' => $this->buildCheckoutPayload($adRequest),
        ], 201);
    }

    public function approved(): JsonResponse
    {
        $ads = $this->adRequestService->listApproved();

        return response()->json([
            'message' => 'Approved ads loaded successfully.',
            'status' => 200,
            'data' => AdRequestResource::collection($ads),
        ]);
    }

    public function show(AdRequest $adRequest): JsonResponse
    {
        $this->ensureOwner($adRequest);

        return response()->json([
            'message' => 'Ad request loaded successfully.',
            'status' => 200,
            'data' => $this->buildCheckoutPayload($adRequest->load('adSpace.service')),
        ]);
    }

    public function cancel(AdRequest $adRequest): JsonResponse
    {
        $this->ensureOwner($adRequest);

        $adRequest = $this->adRequestService->cancel($adRequest);
        $adRequest->loadMissing('adSpace.service', 'media', 'order');

        return response()->json([
            'message' => 'Ad request cancelled successfully.',
            'status' => 200,
            'data' => [
                'order' => $this->buildSimpleAdOrder($adRequest->order, $adRequest, $this->adRequestService->buildSummary($adRequest)),
                'ad_space_available' => (bool) data_get($adRequest, 'adSpace.is_available'),
            ],
        ]);
    }

    private function ensureOwner(AdRequest $adRequest): void
    {
        if ($adRequest->user_id !== auth()->id()) {
            throw new HttpResponseException(response()->json([
                'message' => 'This ad request does not belong to the authenticated user.',
                'status' => 403,
            ], 403));
        }
    }

    private function buildCheckoutPayload(AdRequest $adRequest): array
    {
        $adRequest->loadMissing('adSpace.service', 'media', 'order');
        $order = $adRequest->order;
        $summary = $this->adRequestService->buildSummary($adRequest);

        return [
            'order' => $this->buildSimpleAdOrder($order, $adRequest, $summary),
            'payment_methods' => PaymentMethodResource::collection($this->orderService->availablePaymentMethods()),
        ];
    }

    private function buildSimpleAdOrder(?object $order, AdRequest $adRequest, array $summary): ?array
    {
        if (! $order) {
            return null;
        }

        return [
            'id' => $order->id,
            'status' => $order->status,
            // 'currency' => $order->currency,pay_endpoint
            // 'payment_method' => $order->payment_method,
            // 'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $adRequest->id,
                'type' => 'ad_request',
                'status' => $adRequest->status,
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
