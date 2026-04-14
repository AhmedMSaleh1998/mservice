<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Resources\RestUnitCheckoutResource;
use Modules\Services\Resources\RestUnitDetailResource;
use Modules\Services\Resources\RestUnitListResource;
use Modules\Services\Services\RestUnitService;

class RestUnitsController extends Controller
{
    public function __construct(
        private readonly RestUnitService $restUnitService,
    ) {
    }

    public function index(Request $request): mixed
    {
        $validated = $request->validate([
            'province_id' => 'nullable|integer|exists:provinces,id',
            'province_ids' => 'nullable|array',
            'province_ids.*' => 'integer|exists:provinces,id',
            'room_type' => 'nullable|string|in:single_room,single_rooms,double_room,double_rooms,triple_room,triple_rooms,single_bed',
            'room_types' => 'nullable|array',
            'room_types.*' => 'string|in:single_room,single_rooms,double_room,double_rooms,triple_room,triple_rooms,single_bed',
            'from_date' => 'nullable|date|required_with:to_date|after_or_equal:today',
            'to_date' => 'nullable|date|required_with:from_date|after_or_equal:from_date',
            'page' => 'nullable|integer|min:1',
        ]);

        $units = $this->restUnitService->getList(100, $validated);

        return RestUnitListResource::collection($units);
    }

    public function show(Request $request, $id): RestUnitDetailResource
    {
        $validated = $request->validate([
            'from_date' => 'nullable|date|required_with:to_date|after_or_equal:today',
            'to_date' => 'nullable|date|required_with:from_date|after_or_equal:from_date',
            'room_type' => 'nullable|string|in:single_room,single_rooms,double_room,double_rooms,triple_room,triple_rooms,single_bed',
            'room_types' => 'nullable|array',
            'room_types.*' => 'string|in:single_room,single_rooms,double_room,double_rooms,triple_room,triple_rooms,single_bed',
        ]);

        $restUnit = $this->restUnitService->getDetail((int) $id, $validated);

        return RestUnitDetailResource::make($restUnit);
    }

    public function booking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rest_unit_id' => 'required|exists:rest_units,id',
            'unit_type' => 'required|string|in:single_room,single_rooms,double_room,double_rooms,triple_room,triple_rooms,single_bed',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $booking = $this->restUnitService->createBooking([
            ...$validated,
            'user_id' => auth('sanctum')->id(),
        ]);

        return response()->json([
            'message' => __('Booking request submitted successfully.'),
            'status' => 200,
            'data' => $this->buildCheckoutPayload($booking),
        ], 201);
    }

    public function showBooking(RestUnitBooking $restUnitBooking): JsonResponse
    {
        $this->ensureOwner($restUnitBooking);

        return response()->json([
            'message' => __('Rest unit booking loaded successfully.'),
            'status' => 200,
            'data' => $this->buildCheckoutPayload($restUnitBooking->load('restUnit.province', 'restUnit.media', 'order')),
        ]);
    }

    private function ensureOwner(RestUnitBooking $booking): void
    {
        if ($booking->user_id !== auth()->id()) {
            throw new HttpResponseException(response()->json([
                'message' => __('This rest unit booking does not belong to the authenticated user.'),
                'status' => 403,
            ], 403));
        }
    }

    private function buildCheckoutPayload(RestUnitBooking $booking): array
    {
        $booking->loadMissing('restUnit.province', 'restUnit.media', 'order');
        $order = $booking->order;
        $summary = $this->restUnitService->buildSummary($booking);

        return [
            'order' => $this->buildSimpleRestUnitOrder($order, $booking, $summary),
        ];
    }

    private function buildSimpleRestUnitOrder(?object $order, RestUnitBooking $booking, array $summary): ?array
    {
        if (! $order && $booking->status !== RestUnitBooking::STATUS_PAID_SUCCESSFULLY) {
            return null;
        }

        $isFreeBooking = ! $order && (float) $booking->total_price <= 0;

        return [
            'id' => $order?->id,
            'status' => $order?->status ?? $booking->status,
            'currency' => $order?->currency ?? (string) config('checkout.currency', 'EGP'),
            'payment_method' => $order?->payment_method ?? ($isFreeBooking ? 'free' : null),
            'gateway_status' => $order?->gateway_status ?? ($isFreeBooking ? 'PAID' : null),
            'request' => [
                'id' => $booking->id,
                'type' => 'rest_unit_booking',
                'status' => $booking->status,
                'unit_type' => $booking->unit_type,
                'start_date' => optional($booking->start_date)->toDateString(),
                'end_date' => optional($booking->end_date)->toDateString(),
                'rest_unit' => $booking->restUnit ? RestUnitCheckoutResource::make($booking->restUnit)->resolve() : null,
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order?->amount ?? $booking->total_price,
        ];
    }

    private function buildSimpleItems(Collection $items): array
    {
        return $items
            ->map(static fn (array $item): array => [
                'label' => $item['label'] ?? null,
                'description' => $item['description'] ?? null,
                'amount' => $item['amount'] ?? null,
            ])
            ->values()
            ->all();
    }
}
