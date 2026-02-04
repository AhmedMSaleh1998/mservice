<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PayAdRequest;
use App\Http\Requests\Api\StoreAdRequest;
use Illuminate\Http\JsonResponse;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Resources\AdRequestResource;
use Modules\Ads\Services\AdRequestService;

class AdRequestsController extends Controller
{
    public function __construct(
        private readonly AdRequestService $adRequestService
    ) {
    }

    public function store(StoreAdRequest $request): JsonResponse
    {
        $adRequest = $this->adRequestService->create($request->validated(), auth()->id());

        return response()->json([
            'message' => 'Ad request created successfully.',
            'status' => 200,
            'data' => new AdRequestResource($adRequest),
        ], 201);
    }

    public function approved(): JsonResponse
    {
        $ads = $this->adRequestService->listApproved();

        return response()->json([
            'message' => 'Approved ads loaded successfully.',
            'status' => 200,
            'data' => AdRequestResource::collection($ads),
        ]);
    }

    public function show(AdRequest $adRequest): JsonResponse
    {
        $this->ensureOwner($adRequest);

        return response()->json([
            'message' => 'Ad request loaded successfully.',
            'status' => 200,
            'data' => new AdRequestResource($adRequest->load('adSpace')),
        ]);
    }

    public function pay(PayAdRequest $request, AdRequest $adRequest): JsonResponse
    {
        $this->ensureOwner($adRequest);

        if ($adRequest->status !== 'pending_payment') {
            return response()->json([
                'message' => 'Ad request is not awaiting payment.',
                'status' => 422,
            ], 422);
        }

        $adRequest = $this->adRequestService->markPaid($adRequest, $request->validated()['payment_method']);

        return response()->json([
            'message' => 'Payment submitted successfully.',
            'status' => 200,
            'data' => new AdRequestResource($adRequest),
        ]);
    }

    private function ensureOwner(AdRequest $adRequest): void
    {
        if ($adRequest->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
