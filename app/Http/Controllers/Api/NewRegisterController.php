<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewRegisterRequest;
use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Services\NewRegisterService;

class NewRegisterController extends Controller
{
    public function __construct(
        private readonly NewRegisterService $newRegisterService
    )
    {
    }

    public function register(NewRegisterRequest $request)
    {
        try {
            $dto = NewRegisterDTO::fromRequest($request);
            $registrationRequest = $this->newRegisterService->register($dto);

            return response()->json([
                'success' => true,
                'message' => __('Registration successfully'),
                'reg_code' => $registrationRequest->reg_code,
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Registration failed. Please try again.'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
