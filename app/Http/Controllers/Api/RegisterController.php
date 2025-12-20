<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use Modules\Users\Dto\RegisterDTO;
use Modules\Users\Services\AuthService;

class RegisterController extends Controller
{
    public function __construct(
        protected AuthService $authService
    )
    {
    }

    public function register(RegisterRequest $request)
    {
        $dto = RegisterDto::fromRequest($request);
        if ($user = $this->authService->register($dto)) {
            event(new UserRegistered($user));
            return response()->json([
                'message' => __('Registered successfully'),
                'status' => 200,
            ], 200);
        }
    }
}
