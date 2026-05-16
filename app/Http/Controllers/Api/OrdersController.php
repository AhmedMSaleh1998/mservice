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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Services\AdRequestService;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Certificates\Services\CertificateRequestService;
use Modules\Core\Models\Order;
use Modules\Core\Resources\OrderResource;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\CourseBookingService;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Memberships\Services\MembershipService;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Services\RestUnitService;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Services\TravelService;
use Modules\Users\Resources\UserAddressResource;
use Throwable;

class OrdersController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AdRequestService $adRequestService,
        private readonly CourseBookingService $courseBookingService,
        private readonly CertificateRequestService $certificateRequestService,
        private readonly MembershipService $membershipService,
        private readonly RestUnitService $restUnitService,
        private readonly TravelService $travelService,
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
                $checkout = $this->fawryHostedCheckoutService->createCheckout(
                    $order,
                    $request->user(),
                    (string) $request->header('lang', app()->getLocale())
                );

                $order = $this->orderService->recordFawryCheckout($order, $checkout);
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

        Log::info('Fawry return callback received', [
            'merchant_ref_num' => $merchantRefNum,
            'status_code' => data_get($payload, 'statusCode'),
            'status_description' => data_get($payload, 'statusDescription'),
            'order_status' => data_get($payload, 'orderStatus'),
            'method' => $request->method(),
            'query' => $request->query(),
            'payload' => Arr::except($payload, ['signature']),
            'has_signature' => filled(data_get($payload, 'signature')),
        ]);

        $order = $merchantRefNum !== '' ? $this->orderService->findByMerchantReference($merchantRefNum) : null;
        $statusCode = (int) data_get($payload, 'statusCode', 200);
        $signatureValid = $order !== null
            && $statusCode === 200
            && $this->fawryHostedCheckoutService->verifyReturnSignature($payload);

        if ($signatureValid) {
            $order = $this->applyFawryPaymentUpdate($order, $payload, 'return_response');
        }

        if (! $signatureValid) {
            Log::warning('Fawry return could not be verified or completed', [
                'merchant_ref_num' => $merchantRefNum,
                'status_code' => $statusCode,
                'order_found' => $order !== null,
                'payload' => Arr::except($payload, ['signature']),
            ]);
        }

        $paymentSuccessful = $signatureValid && $order?->status === 'paid_successfully';
        $orderable = $order?->orderable;
        $redirectUrl = $this->fawryHostedCheckoutService->frontendReturnUrl([
            'order_id' => $order?->id,
            'ad_request_id' => $orderable instanceof AdRequest ? $orderable->id : null,
            'certificate_request_id' => $orderable instanceof CertificateRequest ? $orderable->id : null,
            'course_booking_id' => $orderable instanceof CourseBooking ? $orderable->id : null,
            'membership_request_id' => $orderable instanceof MembershipRequest ? $orderable->id : null,
            'rest_unit_booking_id' => $orderable instanceof RestUnitBooking ? $orderable->id : null,
            'travel_booking_id' => $orderable instanceof TravelBooking ? $orderable->id : null,
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
        
        if (! $success || ! $order) {
            Log::warning('Fawry result reported unsuccessful payment or unresolved order', [
                'order_found' => $order !== null,
                'query' => $request->query(),
            ]);
        }

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
                'certificate_request_id' => $orderable instanceof CertificateRequest ? $orderable->id : null,
                'course_booking_id' => $orderable instanceof CourseBooking ? $orderable->id : null,
                'membership_request_id' => $orderable instanceof MembershipRequest ? $orderable->id : null,
                'rest_unit_booking_id' => $orderable instanceof RestUnitBooking ? $orderable->id : null,
                'travel_booking_id' => $orderable instanceof TravelBooking ? $orderable->id : null,
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

        if ($orderable instanceof MembershipRequest && $orderable->status !== 'pending_payment') {
            throw new HttpResponseException(response()->json([
                'message' => 'Membership request is not awaiting payment.',
                'status' => 422,
            ], 422));
        }

        if ($orderable instanceof CertificateRequest && $orderable->status !== CertificateRequest::STATUS_PENDING_PAYMENT) {
            throw new HttpResponseException(response()->json([
                'message' => 'Certificate request is not awaiting payment.',
                'status' => 422,
            ], 422));
        }

        if ($orderable instanceof RestUnitBooking) {
            if ($this->restUnitService->expireReservation($orderable)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Rest unit booking reservation has expired.',
                    'status' => 422,
                ], 422));
            }

            $orderable = $orderable->fresh();

            if ($orderable?->status === RestUnitBooking::STATUS_PAYMENT_EXPIRED) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Rest unit booking reservation has expired.',
                    'status' => 422,
                ], 422));
            }

            if ($orderable?->status !== RestUnitBooking::STATUS_PENDING_PAYMENT) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Rest unit booking is not awaiting payment.',
                    'status' => 422,
                ], 422));
            }
        }

        if ($orderable instanceof TravelBooking) {
            if ($this->travelService->expireReservation($orderable)) {
                throw new HttpResponseException(response()->json([
                    'message' => __('Travel booking reservation has expired.'),
                    'status' => 422,
                ], 422));
            }

            $orderable = $orderable->fresh();

            if ($orderable?->status === TravelBooking::STATUS_PAYMENT_EXPIRED) {
                throw new HttpResponseException(response()->json([
                    'message' => __('Travel booking reservation has expired.'),
                    'status' => 422,
                ], 422));
            }

            if ($orderable?->status !== TravelBooking::STATUS_PENDING_PAYMENT) {
                throw new HttpResponseException(response()->json([
                    'message' => __('Travel booking is not awaiting payment.'),
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

        if ($orderable instanceof MembershipRequest) {
            $orderable->loadMissing('userAddress.province', 'order');
            $summary = $this->membershipService->buildSummary($orderable);

            $payload['order'] = $this->buildSimpleMembershipOrder($order, $orderable, $summary);
            $payload['payment_methods'] = PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
            $payload['checkout'] = $checkout;
            $payload['actions'] = array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'confirm_payment_endpoint' => $order->payment_method === 'fawry'
                    ? null
                    : route('api.orders.confirm-payment', $order),
                'sync_payment_status_endpoint' => $order->payment_method === 'fawry'
                    ? route('api.orders.sync-payment', $order)
                    : null,
            ]);

            return $payload;
        }

        if ($orderable instanceof CertificateRequest) {
            $orderable->loadMissing('certificate.media', 'userAddress.province', 'order');
            $summary = $this->certificateRequestService->buildSummary($orderable);

            $payload['order'] = $this->buildSimpleCertificateOrder($order, $orderable, $summary);
            $payload['payment_methods'] = PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
            $payload['checkout'] = $checkout;
            $payload['actions'] = array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'confirm_payment_endpoint' => $order->payment_method === 'fawry'
                    ? null
                    : route('api.orders.confirm-payment', $order),
                'sync_payment_status_endpoint' => $order->payment_method === 'fawry'
                    ? route('api.orders.sync-payment', $order)
                    : null,
            ]);

            return $payload;
        }

        if ($orderable instanceof RestUnitBooking) {
            $orderable->loadMissing('restUnit.province', 'restUnit.media', 'order');
            $summary = $this->restUnitService->buildSummary($orderable);

            $payload['order'] = $this->buildSimpleRestUnitOrder($order, $orderable, $summary);
            $payload['payment_methods'] = $order->status === 'paid_successfully'
                ? []
                : PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
            $payload['checkout'] = $checkout;
            $payload['actions'] = array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'confirm_payment_endpoint' => $order->payment_method === 'fawry'
                    ? null
                    : route('api.orders.confirm-payment', $order),
                'sync_payment_status_endpoint' => $order->payment_method === 'fawry'
                    ? route('api.orders.sync-payment', $order)
                    : null,
            ]);

            return $payload;
        }

        if ($orderable instanceof TravelBooking) {
            $orderable->loadMissing('travel.province', 'travel.categories', 'items.category', 'order');
            $summary = $this->travelService->buildSummary($orderable);

            $payload['order'] = $this->buildSimpleTravelOrder($order, $orderable, $summary);
            $payload['payment_methods'] = $order->status === 'paid_successfully'
                ? []
                : PaymentMethodResource::collection($this->orderService->availablePaymentMethods());
            $payload['checkout'] = $checkout;
            $payload['actions'] = array_filter([
                'pay_endpoint' => route('api.orders.pay', $order),
                'confirm_payment_endpoint' => $order->payment_method === 'fawry'
                    ? null
                    : route('api.orders.confirm-payment', $order),
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

    private function buildSimpleMembershipOrder(Order $order, MembershipRequest $membershipRequest, array $summary): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $membershipRequest->id,
                'type' => 'membership_request',
                'status' => $membershipRequest->status,
                'delivery_status' => $membershipRequest->delivery_status,
                'full_name' => $membershipRequest->full_name,
                'specialty' => $membershipRequest->specialty,
                'degree' => $membershipRequest->degree,
                'registration_number' => $membershipRequest->registration_number,
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order->amount,
        ];
    }

    private function buildSimpleCertificateOrder(Order $order, CertificateRequest $certificateRequest, array $summary): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $certificateRequest->id,
                'type' => 'certificate_request',
                'status' => $certificateRequest->status,
                'delivery_method' => $certificateRequest->delivery_method,
                'delivery_status' => $certificateRequest->delivery_status,
                'phone' => $certificateRequest->phone,
                'email' => $certificateRequest->email,
                'address' => $certificateRequest->userAddress
                    ? UserAddressResource::make($certificateRequest->userAddress)->resolve()
                    : null,
                'certificate' => $certificateRequest->certificate
                    ? [
                        'id' => $certificateRequest->certificate->id,
                        'name' => $certificateRequest->certificate->name,
                        'price' => $certificateRequest->certificate->price,
                    ]
                    : null,
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order->amount,
        ];
    }

    private function buildSimpleRestUnitOrder(Order $order, RestUnitBooking $restUnitBooking, array $summary): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $restUnitBooking->id,
                'type' => 'rest_unit_booking',
                'status' => $restUnitBooking->status,
                'unit_type' => $restUnitBooking->unit_type,
                'start_date' => optional($restUnitBooking->start_date)->toDateString(),
                'end_date' => optional($restUnitBooking->end_date)->toDateString(),
                'rest_unit_id' => $restUnitBooking->rest_unit_id,
                'paid_at' => optional($restUnitBooking->paid_at)->format('Y-m-d H:i:s'),
            ],
            'items' => $this->buildSimpleItems(collect($summary['items'] ?? [])),
            'total' => $summary['total'] ?? $order->amount,
        ];
    }

    private function buildSimpleTravelOrder(Order $order, TravelBooking $travelBooking, array $summary): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'gateway_status' => $order->gateway_status,
            'request' => [
                'id' => $travelBooking->id,
                'type' => 'travel_booking',
                'status' => $travelBooking->status,
                'participants_count' => $travelBooking->participants_count,
                'paid_at' => optional($travelBooking->paid_at)->format('Y-m-d H:i:s'),
                'travel_id' => $travelBooking->travel_id,
                'travel' => $travelBooking->travel ? [
                    'id' => $travelBooking->travel->id,
                    'title' => $travelBooking->travel->title,
                    'location' => $travelBooking->travel->location,
                    'province' => [
                        'id' => $travelBooking->travel->province_id,
                        'name' => data_get($travelBooking->travel, 'province.name'),
                    ],
                    'start_date' => optional($travelBooking->travel->start_date)->toDateString(),
                    'end_date' => optional($travelBooking->travel->end_date)->toDateString(),
                    'image_url' => $travelBooking->travel->getFirstMedia('image')?->getFullUrl()
                        ?: $travelBooking->travel->getFirstMedia('cover_image')?->getFullUrl(),
                ] : null,
                'categories' => $travelBooking->items
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

        if ($orderable instanceof MembershipRequest) {
            $orderable->loadMissing('userAddress.province', 'order');

            return $this->buildSimpleMembershipOrder($order, $orderable, $this->membershipService->buildSummary($orderable));
        }

        if ($orderable instanceof CertificateRequest) {
            $orderable->loadMissing('certificate.media', 'userAddress.province', 'order');

            return $this->buildSimpleCertificateOrder($order, $orderable, $this->certificateRequestService->buildSummary($orderable));
        }

        if ($orderable instanceof RestUnitBooking) {
            $orderable->loadMissing('restUnit.province', 'restUnit.media', 'order');

            return $this->buildSimpleRestUnitOrder($order, $orderable, $this->restUnitService->buildSummary($orderable));
        }

        if ($orderable instanceof TravelBooking) {
            $orderable->loadMissing('travel.province', 'travel.categories', 'items.category', 'order');

            return $this->buildSimpleTravelOrder($order, $orderable, $this->travelService->buildSummary($orderable));
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

        if ($orderable instanceof CertificateRequest) {
            return 'certificate_request';
        }

        if ($orderable instanceof MembershipRequest) {
            return 'membership_request';
        }

        if ($orderable instanceof RestUnitBooking) {
            return 'rest_unit_booking';
        }

        if ($orderable instanceof TravelBooking) {
            return 'travel_booking';
        }

        if ($request->filled('ad_request_id')) {
            return 'ad_request';
        }

        if ($request->filled('course_booking_id')) {
            return 'course_booking';
        }

        if ($request->filled('certificate_request_id')) {
            return 'certificate_request';
        }

        if ($request->filled('membership_request_id')) {
            return 'membership_request';
        }

        if ($request->filled('rest_unit_booking_id')) {
            return 'rest_unit_booking';
        }

        if ($request->filled('travel_booking_id')) {
            return 'travel_booking';
        }

        return null;
    }

    private function resolveFawryResultRequestId(Request $request, ?Order $order): ?int
    {
        $orderable = $order?->orderable;

        if (
            $orderable instanceof AdRequest
            || $orderable instanceof CourseBooking
            || $orderable instanceof CertificateRequest
            || $orderable instanceof MembershipRequest
            || $orderable instanceof RestUnitBooking
            || $orderable instanceof TravelBooking
        ) {
            return $orderable->id;
        }

        $requestId = $request->query(
            'ad_request_id',
            $request->query(
                'course_booking_id',
                $request->query(
                    'certificate_request_id',
                    $request->query(
                        'membership_request_id',
                        $request->query('rest_unit_booking_id', $request->query('travel_booking_id'))
                    )
                )
            )
        );

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

        if ($orderable instanceof RestUnitBooking && $orderStatus === 'PAID' && $this->isLateRestUnitBookingPayment($orderable, $payload)) {
            $expiredPayload = $payload;
            $expiredPayload['originalOrderStatus'] = $orderStatus;
            $expiredPayload['latePaymentRejected'] = true;
            $expiredPayload['orderStatus'] = 'EXPIRED';

            $order = $this->orderService->applyFawryPaymentUpdate($order, $expiredPayload, $source);
            $this->restUnitService->expireReservation($orderable);

            return $order->fresh(['orderable', 'user']);
        }

        if ($orderable instanceof TravelBooking && $orderStatus === 'PAID' && $this->isLateTravelBookingPayment($orderable, $payload)) {
            $expiredPayload = $payload;
            $expiredPayload['originalOrderStatus'] = $orderStatus;
            $expiredPayload['latePaymentRejected'] = true;
            $expiredPayload['orderStatus'] = 'EXPIRED';

            $order = $this->orderService->applyFawryPaymentUpdate($order, $expiredPayload, $source);
            $this->travelService->expireReservation($orderable);

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

    private function isLateRestUnitBookingPayment(RestUnitBooking $restUnitBooking, array $payload): bool
    {
        $reservationExpiresAt = $this->restUnitService->reservationExpiresAt($restUnitBooking);
        $paymentTime = data_get($payload, 'paymentTime');

        if (is_numeric($paymentTime)) {
            return Carbon::createFromTimestampMs((int) $paymentTime)->greaterThan($reservationExpiresAt);
        }

        return Carbon::now()->greaterThan($reservationExpiresAt);
    }

    private function isLateTravelBookingPayment(TravelBooking $travelBooking, array $payload): bool
    {
        $reservationExpiresAt = $this->travelService->reservationExpiresAt($travelBooking);
        $paymentTime = data_get($payload, 'paymentTime');

        if (is_numeric($paymentTime)) {
            return Carbon::createFromTimestampMs((int) $paymentTime)->greaterThan($reservationExpiresAt);
        }

        return Carbon::now()->greaterThan($reservationExpiresAt);
    }
}
