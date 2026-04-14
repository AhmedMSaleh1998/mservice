<?php

namespace Modules\Courses\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\OrderService;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Users\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CourseBookingService
{
    private readonly SubscriptionChargeService $subscriptionChargeService;

    public function __construct(
        private readonly OrderService $orderService,
        ?SubscriptionChargeService $subscriptionChargeService = null,
    ) {
        $this->subscriptionChargeService = $subscriptionChargeService ?? app(SubscriptionChargeService::class);
    }

    public function create(Course $course, int $userId, array $data = []): CourseBooking
    {
        $user = User::query()->findOrFail($userId);
        $subscriptionCharge = $this->resolveSubscriptionCharge($user);

        return DB::transaction(function () use ($course, $user, $data, $subscriptionCharge): CourseBooking {
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

            $existingBooking = CourseBooking::query()
                ->lockForUpdate()
                ->where('user_id', $user->id)
                ->where('course_id', $lockedCourse->id)
                ->where('status', '!=', 'payment_expired')
                ->first();

            if ($existingBooking && $existingBooking->status === 'pending_payment' && $this->isReservationExpired($existingBooking)) {
                $this->expireReservation($existingBooking);
                $existingBooking = null;
            }

            if ($existingBooking) {
                throw ValidationException::withMessages([
                    'course_id' => __('You have already booked this course.'),
                ]);
            }

            $lockedCourse->decrement('available_count');

            $courseAmount = (float) $lockedCourse->price;
            $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
            $amount = $courseAmount + $subscriptionAmount;
            $isFreeCourse = $this->orderService->isFreeAmount($amount);
            $paidAt = $isFreeCourse ? now() : null;
            $bookingStatus = $isFreeCourse ? 'paid_successfully' : 'pending_payment';
            $pricing = $this->buildPricingSummary($lockedCourse, $courseAmount, $subscriptionCharge);

            $courseBooking = CourseBooking::query()->create([
                'user_id' => $user->id,
                'course_id' => $lockedCourse->id,
                'price' => $courseAmount,
                'total_amount' => $amount,
                'status' => $bookingStatus,
                'paid_at' => $paidAt,
            ]);

            if (! $isFreeCourse) {
                $this->orderService->sync($courseBooking, [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'status' => $bookingStatus,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payload' => $this->orderService->withPricingPayload(
                        $this->orderService->withSubscriptionChargePayload(null, $subscriptionCharge),
                        $pricing,
                    ),
                ]);
            }

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
        $order = $courseBooking->relationLoaded('order')
            ? $courseBooking->getRelation('order')
            : null;

        if ($order && ($summary = $this->orderService->pricingSummary($order))) {
            return $summary;
        }

        $title = (string) data_get($courseBooking, 'course.title', __('Course'));
        $courseAmount = (float) $courseBooking->price;
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
                    'amount' => $this->formatMoney($courseAmount),
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

    private function resolveSubscriptionCharge(User $user): array
    {
        try {
            return $this->subscriptionChargeService->resolveForUser($user);
        } catch (RuntimeException $exception) {
            throw new ServiceUnavailableHttpException(
                null,
                __('Unable to verify subscription fees with Oracle at the moment. Please try again later.'),
                $exception,
            );
        }
    }

    private function buildPricingSummary(Course $course, float $courseAmount, array $subscriptionCharge): array
    {
        $subscriptionAmount = (float) ($subscriptionCharge['amount'] ?? 0);
        $totalAmount = $courseAmount + $subscriptionAmount;
        $items = [
            [
                'code' => 'course_booking',
                'description' => (string) ($course->title ?? __('Course')),
                'unit_price' => $this->formatMoney($courseAmount),
                'quantity' => 1,
                'amount' => $this->formatMoney($courseAmount),
            ],
        ];

        if ($subscriptionAmount > 0) {
            $items[] = [
                'code' => 'subscription_fees',
                'unit_price' => $this->formatMoney($subscriptionAmount),
                'quantity' => 1,
                'amount' => $this->formatMoney($subscriptionAmount),
                'meta' => [
                    'subscription_years' => max((int) ($subscriptionCharge['years'] ?? 0), 0),
                ],
            ];
        }

        return [
            'currency' => (string) config('checkout.currency', 'EGP'),
            'items' => $items,
            'subtotal' => $this->formatMoney($totalAmount),
            'discount' => $this->formatMoney(0),
            'fees' => $this->formatMoney(0),
            'total' => $this->formatMoney($totalAmount),
        ];
    }
}
