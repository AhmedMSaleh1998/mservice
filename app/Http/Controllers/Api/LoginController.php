<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\Request;
use Modules\Notifications\Services\DeviceTokenService;
use Modules\Users\Dto\LoginDTO;
use Modules\Users\Resources\AuthUserResource;
use Modules\Users\Services\AuthService;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected DeviceTokenService $deviceTokenService,
    )
    {
    }

    public function login(LoginRequest $request)
    {
        $user = $this->authService->login(LoginDto::fromRequest($request));

        // The app can register its FCM token in the same request as the login.
        if ($request->filled('fcm_token')) {
            $this->deviceTokenService->register(
                $user,
                (string) $request->input('fcm_token'),
                $request->input('platform'),
                $request->header('deviceId'),
            );
        }

        return AuthUserResource::make($user);
    }

    public function logout(Request $request)
    {
        // The app sends its FCM token on logout so the device stops receiving
        // this account's push notifications.
        if ($request->filled('fcm_token') && $request->user()) {
            $this->deviceTokenService->remove($request->user(), (string) $request->input('fcm_token'));
        }

        $this->authService->logout();
        return response()->json([
            'message' => __('Logout successfully'),
        ]);
    }
}
