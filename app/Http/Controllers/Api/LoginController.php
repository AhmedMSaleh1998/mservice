<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\Request;
use Modules\Users\Dto\LoginDTO;
use Modules\Users\Resources\AuthUserResource;
use Modules\Users\Services\AuthService;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    )
    {
    }

    public function login(LoginRequest $request)
    {
        return AuthUserResource::make($this->authService->login(LoginDto::fromRequest($request)));
    }

    public function logout(Request $request)
    {
        // The app sends its FCM token on logout so the device stops receiving
        // this account's push notifications.
        if ($request->filled('fcm_token')) {
            $request->user()?->deviceTokens()->where('token', (string) $request->input('fcm_token'))->delete();
        }

        $this->authService->logout();
        return response()->json([
            'message' => __('Logout successfully'),
        ]);
    }
}
