<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCourseBookingRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\CourseBookingService;

class CourseBookingsController extends Controller
{
    public function __construct(
        private readonly CourseBookingService $courseBookingService,
        private readonly OrderService $orderService,
    ) {
    }

    public function store(StoreCourseBookingRequest $request, Course $course): JsonResponse
    {
        $courseBooking = $this->courseBookingService->create($course, auth()->id(), $request->validated());

        return response()->json([
            'message' => 'Order created successfully.',
            'status' => 200,
            'data' => $this->buildCheckoutPayload($courseBooking),
        ], 201);
    }

    public function show(CourseBooking $courseBooking): JsonResponse
    {
        $this->ensureOwner($courseBooking);

        return response()->json([
            'message' => 'Course booking loaded successfully.',
            'status' => 200,
            'data' => $this->buildCheckoutPayload($courseBooking->load('course')),
        ]);
    }

    private function ensureOwner(CourseBooking $courseBooking): void
    {
        if ($courseBooking->user_id !== auth()->id()) {
            throw new HttpResponseException(response()->json([
                'message' => 'This course booking does not belong to the authenticated user.',
                'status' => 403,
            ], 403));
        }
    }

    private function buildCheckoutPayload(CourseBooking $courseBooking): array
    {
        $courseBooking->loadMissing('course', 'order');
        $order = $courseBooking->order;
        $summary = $this->courseBookingService->buildSummary($courseBooking);

        return [
            'order' => $this->buildSimpleCourseOrder($order, $courseBooking, $summary),
            'payment_methods' => PaymentMethodResource::collection($this->orderService->availablePaymentMethods()),
        ];
    }

    private function buildSimpleCourseOrder(?object $order, CourseBooking $courseBooking, array $summary): ?array
    {
        if (! $order) {
            return null;
        }

        return [
            'id' => $order->id,
            'status' => $order->status,
            'request' => [
                'id' => $courseBooking->id,
                'type' => 'course_booking',
                'status' => $courseBooking->status,
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
