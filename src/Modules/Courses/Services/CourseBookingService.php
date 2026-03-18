<?php

namespace Modules\Courses\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrderService;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;

class CourseBookingService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function create(Course $course, int $userId, array $data = []): CourseBooking
    {
        return DB::transaction(function () use ($course, $userId, $data): CourseBooking {
            $lockedCourse = Course::query()
                ->lockForUpdate()
                ->findOrFail($course->id);

            if (! $lockedCourse->is_active) {
                throw ValidationException::withMessages([
                    'course_id' => __('This course is not available for booking.'),
                ]);
            }

            if ((int) $lockedCourse->available_count < 1) {
                throw ValidationException::withMessages([
                    'course_id' => __('This course is fully booked.'),
                ]);
            }

            $lockedCourse->decrement('available_count');

            $amount = (float) $lockedCourse->price;

            $courseBooking = CourseBooking::query()->create([
                'user_id' => $userId,
                'course_id' => $lockedCourse->id,
                'price' => $amount,
                'total_amount' => $amount,
                'status' => 'pending_payment',
            ]);

            $this->orderService->sync($courseBooking, [
                'user_id' => $userId,
                'amount' => $amount,
                'status' => 'pending_payment',
                'payment_method' => $data['payment_method'] ?? null,
            ]);

            return $courseBooking->fresh(['course', 'order']);
        });
    }

    public function reservationTimeoutMinutes(): int
    {
        $minutes = (int) config('checkout.reservation_timeout_minutes', 5);

        return $minutes > 0 ? $minutes : 5;
    }

    public function reservationExpiresAt(CourseBooking $courseBooking): Carbon
    {
        $createdAt = $courseBooking->created_at instanceof Carbon
            ? $courseBooking->created_at->copy()
            : Carbon::now();

        return $createdAt->addMinutes($this->reservationTimeoutMinutes());
    }

    public function isReservationExpired(CourseBooking $courseBooking): bool
    {
        return $this->reservationExpiresAt($courseBooking)->lte(Carbon::now());
    }

    public function expireReservation(CourseBooking $courseBooking): bool
    {
        return DB::transaction(function () use ($courseBooking): bool {
            $lockedBooking = CourseBooking::query()
                ->lockForUpdate()
                ->find($courseBooking->id);

            if (! $lockedBooking || $lockedBooking->status !== 'pending_payment') {
                return false;
            }

            if (! $this->isReservationExpired($lockedBooking)) {
                return false;
            }

            $order = $lockedBooking->order()->lockForUpdate()->first();
            if ($order && $order->status === 'paid_successfully') {
                return false;
            }

            $course = Course::query()
                ->lockForUpdate()
                ->find($lockedBooking->course_id);

            if ($course) {
                $course->increment('available_count');
            }

            $lockedBooking->status = 'payment_expired';
            $lockedBooking->save();

            if ($order) {
                $gatewayStatus = $order->status === 'paid_successfully'
                    ? $order->gateway_status
                    : 'EXPIRED';

                $order->forceFill([
                    'status' => 'payment_expired',
                    'gateway_status' => $gatewayStatus,
                    'checkout_url' => null,
                    'payment_last_synced_at' => now(),
                ])->save();
            }

            return true;
        });
    }

    public function buildSummary(CourseBooking $courseBooking): array
    {
        $courseBooking->loadMissing('course');

        $title = (string) data_get($courseBooking, 'course.title', __('Course'));
        $totalAmount = (float) $courseBooking->total_amount;

        return [
            'title' => __('Payment Summary'),
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => [
                [
                    'code' => 'course_booking',
                    'label' => __('Course booking'),
                    'description' => $title,
                    'unit_price' => $this->formatMoney($courseBooking->price),
                    'quantity' => 1,
                    'amount' => $this->formatMoney($totalAmount),
                ],
            ],
            'subtotal' => $this->formatMoney($totalAmount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($totalAmount),
        ];
    }

    private function formatMoney(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
