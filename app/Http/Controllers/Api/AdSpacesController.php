<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Ads\Resources\AdSpaceResource;
use Modules\Ads\Services\AdSpaceService;

class AdSpacesController extends Controller
{
    public function __construct(
        private readonly AdSpaceService $adSpaceService
    ) {
    }

    public function index()
    {
        return response()->json([
            'message' => 'Ad spaces loaded successfully.',
            'status' => 200,
            'data' => AdSpaceResource::collection($this->adSpaceService->listAll()),
        ]);
    }
}
