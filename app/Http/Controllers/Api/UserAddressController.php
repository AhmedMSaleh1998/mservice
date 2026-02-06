<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserAddressRequest;
use App\Http\Requests\UpdateUserAddressRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Models\UserAddress;
use Modules\Users\Resources\UserAddressResource;
use Modules\Users\Services\UserAddressService;

class UserAddressController extends Controller
{
    public function __construct(
        private readonly UserAddressService $userAddressService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->latest()->with('province')->get();

        return response()->json([
            'message' => 'data loaded successfully',
            'status' => 200,
            'data' => UserAddressResource::collection($addresses),
        ]);
    }

    public function store(StoreUserAddressRequest $request): JsonResponse
    {
        $address = $this->userAddressService->create($request->user(), $request->validated());

        return response()->json([
            'message' => 'Address created successfully',
            'data' => UserAddressResource::make($address),
        ], 201);
    }

    public function update(UpdateUserAddressRequest $request, UserAddress $userAddress): JsonResponse
    {
        $address = $this->userAddressService->update($request->user(), $userAddress, $request->validated());

        return response()->json([
            'message' => 'Address updated successfully',
            'data' => UserAddressResource::make($address),
        ]);
    }
}
