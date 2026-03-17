<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMembershipRequest;
use Modules\Memberships\Services\MembershipService;
use Illuminate\Http\JsonResponse;

class MembershipController extends Controller
{
    public function __construct(
        protected MembershipService $membershipService
    ) {}

    public function store(StoreMembershipRequest $request): JsonResponse
    {
        // Calculate price first if needed or just create directly.
        // User said "take doctor details ... to make an id ... then return the price of servce".
        
        $membershipRequest = $this->membershipService->createRequest($request->validated(), auth()->id() ?? 0); // Handle unauth for now if needed, but constrained() enforces it.

        return response()->json([
            'message' => 'Membership request created successfully',
            'status' => 200,
            'data' => new \Modules\Memberships\Resources\MembershipRequestResource($membershipRequest->loadMissing('userAddress', 'order'))
        ], 201);
    }
}
