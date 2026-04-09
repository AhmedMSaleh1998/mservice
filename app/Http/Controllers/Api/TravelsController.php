<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTravelBookingRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;
use Modules\Travels\Models\Travel;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Resources\TravelListCollection;
use Modules\Travels\Resources\TravelDetailResource;
use Modules\Travels\Services\TravelService;

class TravelsController extends Controller
{
    public function __construct(
        private readonly TravelService $travelService,
        private readonly OrderService $orderService,
    ) {
    }

    public function index(Request $request): mixed
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'province_id' => 'nullable|integer|exists:provinces,id',
            'province_ids' => 'nullable|array',
            'province_ids.*' => 'integer|exists:provinces,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $travels = $this->travelService->getList(100, $validated);

        return TravelListCollection::make($travels);
    }

    public function show(Travel $travel): TravelDetailResource
    {
        return TravelDetailResource::make($this->travelService->getDetail($travel->id));
    }

    public function booking(StoreTravelBookingRequest $request, Travel $travel): JsonResponse
    {
        $booking = $this->travelService->createBooking($travel, (int) auth('sanctum')->id(), $request->validated());

        return response()->json([
            'message' => __('Travel booking created successfully.'),
            'status' => 200,
            'data' => $this->buildCheckoutPayload($booking),
        ], 201);
    }

    public function showBooking(TravelBooking $travelBooking): JsonResponse
    {
        $this->ensureOwner($travelBooking);

        return response()->json([
            'message' => __('Travel booking loaded successfully.'),
            'status' => 200,
            'data' => $this->buildCheckoutPayload($travelBooking->load('travel.province', 'travel.categories', 'items.category', 'order')),
        ]);
    }

    private function ensureOwner(TravelBooking $booking): void
    {
        if ($booking->user_id !== auth()->id()) {
            throw new HttpResponseException(response()->json([
                'message' => __('This travel booking does not belong to the authenticated user.'),
                'status' => 403,
            ], 403));
        }
    }

    private function buildCheckoutPayload(TravelBooking $booking): array
    {
        $booking->loadMissing('travel.province', 'travel.categories', 'items.category', 'order');
        $order = $booking->order;
        $summary = $this->travelService->buildSummary($booking);

        return [
            'order' => $this->buildSimpleTravelOrder($order, $booking, $summary),
            'payment_methods' => $order && $order->status === 'paid_successfully'
                ? []
                : PaymentMethodResource::collection($this->orderService->availablePaymentMethods()),
            'actions' => $order ? array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'confirm_payment_endpoint' => filled($order->payment_method) && $order->payment_method !== 'fawry'
                    ? route('api.orders.confirm-payment', $order)
                    : null,
                'sync_payment_status_endpoint' => $order->payment_method === 'fawry'
                    ? route('api.orders.sync-payment', $order)
                    : null,
            ]) : [],
        ];
    }

    private function buildSimpleTravelOrder(?object $order, TravelBooking $booking, array $summary): ?array
    {
        if (! $order) {
            return null;
        }

        $travel = $booking->travel;

        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $booking->id,
                'type' => 'travel_booking',
                'status' => $booking->status,
                'participants_count' => $booking->participants_count,
                'paid_at' => optional($booking->paid_at)->format('Y-m-d H:i:s'),
                'travel' => $travel ? [
                    'id' => $travel->id,
                    'title' => $travel->title,
                    'location' => $travel->location,
                    'province' => [
                        'id' => $travel->province_id,
                        'name' => data_get($travel, 'province.name'),
                    ],
                    'start_date' => optional($travel->start_date)->toDateString(),
                    'end_date' => optional($travel->end_date)->toDateString(),
                    'image_url' => $travel->getFirstMedia('image')?->getFullUrl() ?: $travel->getFirstMedia('cover_image')?->getFullUrl(),
                ] : null,
                'categories' => $booking->items
                    ->map(fn ($item): array => [
                        'travel_category_id' => $item->travel_category_id,
                        'code' => $item->category_code,
                        'label' => $item->category_name,
                        'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                        'quantity' => (int) $item->quantity,
                        'amount' => number_format((float) $item->total_price, 2, '.', ''),
                    ])
                    ->values()
                    ->all(),
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order->amount,
        ];
    }

    private function buildSimpleItems(Collection $items): array
    {
        return $items
            ->map(static fn (array $item): array => [
                'code' => $item['code'] ?? null,
                'label' => $item['label'] ?? null,
                'description' => $item['description'] ?? null,
                'amount' => $item['amount'] ?? null,
            ])
            ->values()
            ->all();
    }
}
