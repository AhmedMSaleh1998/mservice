<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserAddressRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Users\Resources\UserAddressResource;

class UserAddressController extends Controller
{
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
        $address = $request->user()->addresses()->create($request->validated());

        return response()->json([
            'message' => 'Address created successfully',
            'data' => UserAddressResource::make($address),
        ], 201);
    }
}
