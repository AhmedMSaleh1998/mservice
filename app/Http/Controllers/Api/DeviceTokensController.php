<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Notifications\Services\DeviceTokenService;

class DeviceTokensController extends Controller
{
    public function __construct(
        private readonly DeviceTokenService $deviceTokenService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', Rule::in(DeviceTokenService::PLATFORMS)],
        ]);

        $this->deviceTokenService->register(
            $request->user(),
            $validated['token'],
            $validated['platform'] ?? null,
            $request->header('deviceId'),
        );

        return response()->json([
            'message' => __('Device token saved successfully.'),
            'status' => 200,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $this->deviceTokenService->remove($request->user(), $validated['token']);

        return response()->json([
            'message' => __('Device token removed successfully.'),
            'status' => 200,
        ]);
    }
}
