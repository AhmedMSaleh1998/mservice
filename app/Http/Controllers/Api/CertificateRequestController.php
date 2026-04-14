<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CertificateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Certificates\Services\CertificateRequestService;
use Modules\Core\Resources\PaymentMethodResource;
use Modules\Core\Services\OrderService;
use Modules\Certificates\Models\CertificateRequest as CertificateRequestModel;
use Modules\Users\Resources\UserAddressResource;

class CertificateRequestController extends Controller
{
    public function __construct(
        protected readonly CertificateRequestService $certificateRequestService,
        protected readonly OrderService $orderService,
    )
    {

    }

    public function store(CertificateRequest $request): JsonResponse
    {
        $certificateRequest = $this->certificateRequestService->makeRequest($request->validated(), auth()->id());

        return response()->json([
            'message' => 'Order created successfully.',
            'status' => 200,
            'data' => $this->buildCheckoutPayload($certificateRequest),
        ], 201);
    }

    private function buildCheckoutPayload(CertificateRequestModel $certificateRequest): array
    {
        $certificateRequest->loadMissing('certificate.media', 'userAddress.province', 'order');
        $order = $certificateRequest->order;
        $summary = $this->certificateRequestService->buildSummary($certificateRequest);

        return [
            'order' => $this->buildSimpleCertificateOrder($order, $certificateRequest, $summary),
            'payment_methods' => ($order?->status ?? $certificateRequest->status) === 'paid_successfully'
                ? []
                : PaymentMethodResource::collection($this->orderService->availablePaymentMethods()),
        ];
    }

    private function buildSimpleCertificateOrder(?object $order, CertificateRequestModel $certificateRequest, array $summary): ?array
    {
        if (! $order && $certificateRequest->status !== CertificateRequestModel::STATUS_PAID_SUCCESSFULLY) {
            return null;
        }

        return [
            'id' => $order?->id,
            'status' => $order?->status ?? $certificateRequest->status,
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
            'total' => $summary['total'] ?? $order?->amount ?? $certificateRequest->total_amount,
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
