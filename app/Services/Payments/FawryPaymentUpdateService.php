<?php

namespace App\Services\Payments;

use Illuminate\Support\Carbon;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Services\AdRequestService;
use Modules\Core\Models\Order;
use Modules\Core\Services\OrderService;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\CourseBookingService;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Services\RestUnitService;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Services\TravelService;

/**
 * Applies a Fawry payment payload to an order, rejecting payments that landed
 * after their reservation expired. Shared by the API controllers and the
 * scheduled reconciliation command so both paths enforce the same guards.
 */
class FawryPaymentUpdateService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AdRequestService $adRequestService,
        private readonly CourseBookingService $courseBookingService,
        private readonly RestUnitService $restUnitService,
        private readonly TravelService $travelService,
    ) {
    }

    public function apply(Order $order, array $payload, string $source): Order
    {
        $order->loadMissing('orderable');
        $orderable = $order->orderable;
        $orderStatus = strtoupper((string) data_get($payload, 'orderStatus'));

        if ($orderable instanceof AdRequest && $orderStatus === 'PAID' && $this->isLatePayment($this->adRequestService->reservationExpiresAt($orderable), $payload)) {
            $order = $this->orderService->applyFawryPaymentUpdate($order, $this->expiredPayload($payload, $orderStatus), $source);
            $this->adRequestService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        if ($orderable instanceof CourseBooking && $orderStatus === 'PAID' && $this->isLatePayment($this->courseBookingService->reservationExpiresAt($orderable), $payload)) {
            $order = $this->orderService->applyFawryPaymentUpdate($order, $this->expiredPayload($payload, $orderStatus), $source);
            $this->courseBookingService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        if ($orderable instanceof RestUnitBooking && $orderStatus === 'PAID' && $this->isLatePayment($this->restUnitService->reservationExpiresAt($orderable), $payload)) {
            $order = $this->orderService->applyFawryPaymentUpdate($order, $this->expiredPayload($payload, $orderStatus), $source);
            $this->restUnitService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        if ($orderable instanceof TravelBooking && $orderStatus === 'PAID' && $this->isLatePayment($this->travelService->reservationExpiresAt($orderable), $payload)) {
            $order = $this->orderService->applyFawryPaymentUpdate($order, $this->expiredPayload($payload, $orderStatus), $source);
            $this->travelService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        return $this->orderService->applyFawryPaymentUpdate($order, $payload, $source);
    }

    private function expiredPayload(array $payload, string $originalOrderStatus): array
    {
        $payload['originalOrderStatus'] = $originalOrderStatus;
        $payload['latePaymentRejected'] = true;
        $payload['orderStatus'] = 'EXPIRED';

        return $payload;
    }

    private function isLatePayment(Carbon $reservationExpiresAt, array $payload): bool
    {
        $paymentTime = data_get($payload, 'paymentTime');

        if (is_numeric($paymentTime)) {
            return Carbon::createFromTimestampMs((int) $paymentTime)->greaterThan($reservationExpiresAt);
        }

        return Carbon::now()->greaterThan($reservationExpiresAt);
    }
}
