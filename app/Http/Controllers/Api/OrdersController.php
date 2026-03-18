<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PayOrderRequest;
use App\Services\Payments\FawryHostedCheckoutService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Services\AdRequestService;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Resources\OrderResource;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\CourseBookingService;
use Modules\Memberships\Models\MembershipRequest;
use Throwable;

class OrdersController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AdRequestService $adRequestService,
        private readonly CourseBookingService $courseBookingService,
        private readonly FawryHostedCheckoutService $fawryHostedCheckoutService,
    ) {
    }

    public function pay(PayOrderRequest $request, Order $order): JsonResponse
    {
        $this->ensureOwner($order);
        $this->ensurePendingPayment($order);

        $paymentMethod = $request->validated()['payment_method'];

        try {
            if ($paymentMethod === 'fawry' && $this->fawryHostedCheckoutService->isEnabled()) {
                $checkout = $this->orderService->reusableFawryCheckout($order);

                if (! $checkout) {
                    $checkout = $this->fawryHostedCheckoutService->createCheckout(
                        $order,
                        $request->user(),
                        (string) $request->header('lang', app()->getLocale())
                    );

                    $order = $this->orderService->recordFawryCheckout($order, $checkout);
                } else {
                    $order = $order->fresh(['orderable', 'user']);
                }
            } else {
                $checkout = $this->orderService->startCheckout($order, $paymentMethod);
                $order = $order->fresh(['orderable', 'user']);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('Unable to create checkout at the moment.'),
            ], 502);
        }

        if ($paymentMethod === 'fawry' && filled($checkout['payment_url'] ?? null)) {
            return response()->json([
                'payment_url' => $checkout['payment_url'],
            ]);
        }

        return response()->json([
            'message' => $paymentMethod === 'fawry' && $this->fawryHostedCheckoutService->isEnabled()
                ? 'Fawry checkout initialized successfully.'
                : 'Checkout initialized successfully.',
            'status' => 200,
            'data' => $this->buildOrderPayload($order, $checkout),
        ]);
    }

    public function confirmPayment(Order $order): JsonResponse
    {
        $this->ensureOwner($order);
        $this->ensurePendingPayment($order);

        if (blank($order->payment_method)) {
            return response()->json([
                'message' => 'Checkout has not been started for this order.',
                'status' => 422,
            ], 422);
        }

        if ($order->payment_method === 'fawry' && $this->fawryHostedCheckoutService->isEnabled()) {
            return response()->json([
                'message' => __('Manual confirmation is not available for Fawry checkout.'),
                'status' => 422,
            ], 422);
        }

        if (! config('checkout.mock_enabled', true)) {
            return response()->json([
                'message' => 'Mock checkout is disabled.',
                'status' => 503,
            ], 503);
        }

        $order = $this->orderService->confirmPayment($order);

        return response()->json([
            'message' => 'Mock payment confirmed successfully.',
            'status' => 200,
            'data' => $this->buildOrderPayload($order),
        ]);
    }

    public function syncPaymentStatus(Order $order): JsonResponse
    {
        $this->ensureOwner($order);

        if ($order->payment_method !== 'fawry') {
            return response()->json([
                'message' => 'Payment sync is only available for Fawry checkout.',
                'status' => 422,
            ], 422);
        }

        try {
            $payload = $this->fawryHostedCheckoutService->pullPaymentStatus($order);
            if (! $this->fawryHostedCheckoutService->verifyStatusSignature($payload)) {
                return response()->json([
                    'message' => 'Invalid Fawry payment status signature.',
                    'status' => 422,
                ], 422);
            }

            $order = $this->applyFawryPaymentUpdate($order, $payload, 'status_response');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('Unable to sync Fawry payment status at the moment.'),
                'status' => 502,
            ], 502);
        }

        return response()->json([
            'message' => __('Payment status synced successfully.'),
            'status' => 200,
            'data' => $this->buildOrderPayload($order),
        ]);
    }

    public function handleFawryReturn(Request $request): JsonResponse|RedirectResponse
    {
        $payload = $request->all();
        $merchantRefNum = (string) data_get($payload, 'merchantRefNumber');
        $order = $merchantRefNum !== '' ? $this->orderService->findByMerchantReference($merchantRefNum) : null;
        $statusCode = (int) data_get($payload, 'statusCode', 200);
        $signatureValid = $order !== null
            && $statusCode === 200
            && $this->fawryHostedCheckoutService->verifyReturnSignature($payload);

        if ($signatureValid) {
            $order = $this->applyFawryPaymentUpdate($order, $payload, 'return_response');
        }

        $paymentSuccessful = $signatureValid && $order?->status === 'paid_successfully';
        $orderable = $order?->orderable;
        $redirectUrl = $this->fawryHostedCheckoutService->frontendReturnUrl([
            'order_id' => $order?->id,
            'ad_request_id' => $orderable instanceof AdRequest ? $orderable->id : null,
            'course_booking_id' => $orderable instanceof CourseBooking ? $orderable->id : null,
            'course_id' => $orderable instanceof CourseBooking ? $orderable->course_id : null,
            'merchant_ref_num' => $merchantRefNum,
            'success' => $paymentSuccessful ? '1' : '0',
            'status_code' => data_get($payload, 'statusCode'),
            'status_description' => data_get($payload, 'statusDescription'),
            'order_status' => data_get($payload, 'orderStatus', $order?->gateway_status),
            'reference_number' => data_get($payload, 'referenceNumber'),
        ]);

        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        $responseStatus = $signatureValid ? 200 : ($statusCode === 200 ? 422 : 400);

        return response()->json([
            'message' => $paymentSuccessful
                ? __('Fawry return processed successfully.')
                : ($signatureValid
                    ? __('Fawry return processed but payment is not completed yet.')
                    : __('Invalid Fawry return signature.')),
            'status' => $responseStatus,
            'data' => $order ? $this->buildOrderPayload($order) : null,
        ], $responseStatus);
    }

    public function showFawryResult(Request $request): JsonResponse
    {
        $order = $this->resolveFawryResultOrder($request);
        $requestType = $this->resolveFawryResultRequestType($request, $order);
        $requestId = $this->resolveFawryResultRequestId($request, $order);
        $success = $request->query('success') === '1';
        $statusCode = (int) $request->query('status_code', 200);
        $orderStatus = (string) $request->query('order_status', $order?->gateway_status);
        $statusDescription = (string) $request->query('status_description', '');

        return response()->json([
            'message' => $success
                ? 'Payment processed successfully.'
                : 'Payment result loaded successfully.',
            'status' => 200,
            'data' => [
                'success' => $success,
                'order' => $order ? $this->buildFawryResultOrderPayload($order) : null,
                'request' => [
                    'id' => $requestId,
                    'type' => $requestType,
                ],
                'payment' => [
                    'status' => $orderStatus,
                    'status_code' => $statusCode,
                    'status_description' => $statusDescription,
                    'merchant_ref_num' => $request->query('merchant_ref_num'),
                    'reference_number' => $request->query('reference_number'),
                ],
            ],
        ]);
    }

    public function handleFawryNotification(Request $request): JsonResponse
    {
        $payload = $request->all();
        $merchantRefNum = (string) data_get($payload, 'merchantRefNumber');

        if ($merchantRefNum === '') {
            return response()->json([
                'message' => 'merchantRefNumber is required.',
                'status' => 422,
            ], 422);
        }

        $order = $this->orderService->findByMerchantReference($merchantRefNum, AdRequest::class);
        if (! $order) {
            $order = $this->orderService->findByMerchantReference($merchantRefNum);
        }

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.',
                'status' => 404,
            ], 404);
        }

        if (! $this->fawryHostedCheckoutService->verifyStatusSignature($payload)) {
            return response()->json([
                'message' => 'Invalid Fawry notification signature.',
                'status' => 422,
            ], 422);
        }

        $order = $this->applyFawryPaymentUpdate($order, $payload, 'notification_response');
        $orderable = $order->orderable;

        return response()->json([
            'message' => __('Fawry notification processed successfully.'),
            'status' => 200,
            'data' => [
                'order_id' => $order->id,
                'ad_request_id' => $orderable instanceof AdRequest ? $orderable->id : null,
                'course_booking_id' => $orderable instanceof CourseBooking ? $orderable->id : null,
                'payment_status' => $order->gateway_status,
            ],
        ]);
    }

    private function ensureOwner(Order $order): void
    {
        if ($order->user_id !== auth()->id()) {
            throw new HttpResponseException(response()->json([
                'message' => 'This order does not belong to the authenticated user.',
                'status' => 403,
            ], 403));
        }
    }

    private function ensurePendingPayment(Order $order): void
    {
        $order->loadMissing('orderable');
        $orderable = $order->orderable;

        if ($orderable instanceof AdRequest) {
            if ($this->adRequestService->expireReservation($orderable)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Ad request reservation has expired.',
                    'status' => 422,
                ], 422));
            }

            $orderable = $orderable->fresh();

            if ($orderable?->status === 'payment_expired') {
                throw new HttpResponseException(response()->json([
                    'message' => 'Ad request reservation has expired.',
                    'status' => 422,
                ], 422));
            }

            if ($orderable?->status !== 'pending_payment') {
                throw new HttpResponseException(response()->json([
                    'message' => 'Ad request is not awaiting payment.',
                    'status' => 422,
                ], 422));
            }
        }

        if ($orderable instanceof CourseBooking) {
            if ($this->courseBookingService->expireReservation($orderable)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Course booking reservation has expired.',
                    'status' => 422,
                ], 422));
            }

            $orderable = $orderable->fresh();

            if ($orderable?->status === 'payment_expired') {
                throw new HttpResponseException(response()->json([
                    'message' => 'Course booking reservation has expired.',
                    'status' => 422,
                ], 422));
            }

            if ($orderable?->status !== 'pending_payment') {
                throw new HttpResponseException(response()->json([
                    'message' => 'Course booking is not awaiting payment.',
                    'status' => 422,
                ], 422));
            }
        }

        if ($order->status === 'paid_successfully') {
            throw new HttpResponseException(response()->json([
                'message' => 'Order is not awaiting payment.',
                'status' => 422,
            ], 422));
        }
    }

    private function buildOrderPayload(Order $order, ?array $checkout = null): array
    {
        $order->loadMissing('orderable', 'user');
        $orderable = $order->orderable;

        $payload = [
            'order' => OrderResource::make($order),
        ];

        if ($orderable instanceof AdRequest) {
            $orderable->loadMissing('adSpace.service', 'media', 'order');
            $summary = $this->adRequestService->buildSummary($orderable);

            $payload['order'] = $this->buildSimpleAdOrder($order, $orderable, $summary);
            $payload['payment_methods'] = PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
            $payload['checkout'] = $checkout;
            $payload['actions'] = array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'sync_payment_status_endpoint' => $order->payment_method === 'fawry'
                    ? route('api.orders.sync-payment', $order)
                    : null,
            ]);

            return $payload;
        }

        if ($orderable instanceof CourseBooking) {
            $orderable->loadMissing('course', 'order');
            $summary = $this->courseBookingService->buildSummary($orderable);

            $payload['order'] = $this->buildSimpleCourseOrder($order, $orderable, $summary);
            $payload['payment_methods'] = PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
            $payload['checkout'] = $checkout;
            $payload['actions'] = array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'sync_payment_status_endpoint' => $order->payment_method === 'fawry'
                    ? route('api.orders.sync-payment', $order)
                    : null,
            ]);

            return $payload;
        }

        $payload['payment_methods'] = PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
        $payload['checkout'] = $checkout;
        $payload['actions'] = [
            'pay_endpoint' => route('api.orders.pay', $order),
            'confirm_payment_endpoint' => route('api.orders.confirm-payment', $order),
            'sync_payment_status_endpoint' => route('api.orders.sync-payment', $order),
        ];

        if ($orderable instanceof MembershipRequest) {
            $orderable->loadMissing('userAddress', 'order');
            $order->setRelation('orderable', $orderable);

            return $payload;
        }

        if ($orderable instanceof CertificateRequest) {
            $orderable->loadMissing('userAddress', 'order');
            $order->setRelation('orderable', $orderable);
        }

        return $payload;
    }

    private function buildSimpleAdOrder(Order $order, AdRequest $adRequest, array $summary): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $adRequest->id,
                'type' => 'ad_request',
                'status' => $adRequest->status,
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order->amount,
        ];
    }

    private function buildSimpleCourseOrder(Order $order, CourseBooking $courseBooking, array $summary): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
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
                'code' => $item['code'] ?? null,
                'label' => $item['label'] ?? null,
                'amount' => $item['amount'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function buildFawryResultOrderPayload(Order $order): array
    {
        $order->loadMissing('orderable', 'user');
        $orderable = $order->orderable;

        if ($orderable instanceof AdRequest) {
            $orderable->loadMissing('adSpace.service', 'media', 'order');

            return $this->buildSimpleAdOrder($order, $orderable, $this->adRequestService->buildSummary($orderable));
        }

        if ($orderable instanceof CourseBooking) {
            $orderable->loadMissing('course', 'order');

            return $this->buildSimpleCourseOrder($order, $orderable, $this->courseBookingService->buildSummary($orderable));
        }

        return OrderResource::make($order)->resolve();
    }

    private function resolveFawryResultOrder(Request $request): ?Order
    {
        $orderId = (int) $request->query('order_id', 0);
        if ($orderId > 0) {
            return Order::query()
                ->with('orderable', 'user')
                ->find($orderId);
        }

        $merchantRefNum = (string) $request->query('merchant_ref_num', '');

        return $merchantRefNum !== ''
            ? $this->orderService->findByMerchantReference($merchantRefNum)
            : null;
    }

    private function resolveFawryResultRequestType(Request $request, ?Order $order): ?string
    {
        $orderable = $order?->orderable;

        if ($orderable instanceof AdRequest) {
            return 'ad_request';
        }

        if ($orderable instanceof CourseBooking) {
            return 'course_booking';
        }

        if ($request->filled('ad_request_id')) {
            return 'ad_request';
        }

        if ($request->filled('course_booking_id')) {
            return 'course_booking';
        }

        return null;
    }

    private function resolveFawryResultRequestId(Request $request, ?Order $order): ?int
    {
        $orderable = $order?->orderable;

        if ($orderable instanceof AdRequest || $orderable instanceof CourseBooking) {
            return $orderable->id;
        }

        $requestId = $request->query('ad_request_id', $request->query('course_booking_id'));

        return is_numeric($requestId) ? (int) $requestId : null;
    }

    private function applyFawryPaymentUpdate(Order $order, array $payload, string $source): Order
    {
        $order->loadMissing('orderable');
        $orderable = $order->orderable;
        $orderStatus = strtoupper((string) data_get($payload, 'orderStatus'));

        if ($orderable instanceof AdRequest && $orderStatus === 'PAID' && $this->isLateFawryPayment($orderable, $payload)) {
            $expiredPayload = $payload;
            $expiredPayload['originalOrderStatus'] = $orderStatus;
            $expiredPayload['latePaymentRejected'] = true;
            $expiredPayload['orderStatus'] = 'EXPIRED';

            $order = $this->orderService->applyFawryPaymentUpdate($order, $expiredPayload, $source);
            $this->adRequestService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        if ($orderable instanceof CourseBooking && $orderStatus === 'PAID' && $this->isLateCourseBookingPayment($orderable, $payload)) {
            $expiredPayload = $payload;
            $expiredPayload['originalOrderStatus'] = $orderStatus;
            $expiredPayload['latePaymentRejected'] = true;
            $expiredPayload['orderStatus'] = 'EXPIRED';

            $order = $this->orderService->applyFawryPaymentUpdate($order, $expiredPayload, $source);
            $this->courseBookingService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        return $this->orderService->applyFawryPaymentUpdate($order, $payload, $source);
    }

    private function isLateFawryPayment(AdRequest $adRequest, array $payload): bool
    {
        $reservationExpiresAt = $this->adRequestService->reservationExpiresAt($adRequest);
        $paymentTime = data_get($payload, 'paymentTime');

        if (is_numeric($paymentTime)) {
            return Carbon::createFromTimestampMs((int) $paymentTime)->greaterThan($reservationExpiresAt);
        }

        return Carbon::now()->greaterThan($reservationExpiresAt);
    }

    private function isLateCourseBookingPayment(CourseBooking $courseBooking, array $payload): bool
    {
        $reservationExpiresAt = $this->courseBookingService->reservationExpiresAt($courseBooking);
        $paymentTime = data_get($payload, 'paymentTime');

        if (is_numeric($paymentTime)) {
            return Carbon::createFromTimestampMs((int) $paymentTime)->greaterThan($reservationExpiresAt);
        }

        return Carbon::now()->greaterThan($reservationExpiresAt);
    }
}
