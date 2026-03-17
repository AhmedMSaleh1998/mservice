<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CertificateRequest;
use Modules\Certificates\Resources\CertificateRequestResource;
use Modules\Certificates\Services\CertificateRequestService;

class CertificateRequestController extends Controller
{
    public function __construct(
        protected readonly CertificateRequestService $certificateRequestService
    )
    {

    }

    public function store(CertificateRequest $request)
    {
        $certificateRequest = $this->certificateRequestService->makeRequest($request->validated(), auth()->id());

        return response()->json([
            'message' => 'Certificate request created successfully',
            'status' => 200,
            'data' => new CertificateRequestResource($certificateRequest->loadMissing('userAddress', 'order'))
        ], 201);
    }
}
