<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Notifications\Models\DeviceToken;

class DeviceTokensController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        // A token identifies one app install: when another account signs in on
        // the same device, the token moves to that account.
        DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? null,
                'device_id' => $request->header('deviceId') ?: null,
                'last_used_at' => now(),
            ],
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

        $request->user()->deviceTokens()->where('token', $validated['token'])->delete();

        return response()->json([
            'message' => __('Device token removed successfully.'),
            'status' => 200,
        ]);
    }
}
