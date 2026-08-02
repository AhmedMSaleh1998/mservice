<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Order;
use Modules\Core\Services\PaymentHistoryService;

class PaymentsController extends Controller
{
    public function __construct(
        private readonly PaymentHistoryService $paymentHistoryService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentHistoryService->listForUser(
            $request->user(),
            $request->only(['search', 'date', 'date_from', 'date_to', 'page', 'per_page']),
            (string) $request->header('lang', app()->getLocale())
        );

        return response()->json([
            'message' => 'Payments loaded successfully.',
            'status' => 200,
            'data' => [
                'items' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->ensureAccessiblePaidOrder($request, $order);

        return response()->json([
            'message' => 'Payment detail loaded successfully.',
            'status' => 200,
            'data' => $this->paymentHistoryService->detailForOrder(
                $order,
                (string) $request->header('lang', app()->getLocale())
            ),
        ]);
    }

    private function ensureAccessiblePaidOrder(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()?->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'This payment does not belong to the authenticated user.',
                'status' => 403,
            ], 403));
        }

        if ($order->status !== 'paid_successfully') {
            throw new HttpResponseException(response()->json([
                'message' => 'This payment is not available yet.',
                'status' => 422,
            ], 422));
        }
    }
}
