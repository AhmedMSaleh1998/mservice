<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Certificates\Resources\CertificateResource;
use Modules\Certificates\Services\CertificateService;

class CertificatesController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificateService
    )
    {
    }

    public function index()
    {
        return response()->json([
                'message' => 'certificate list loaded successfully.',
                'status' => 200,
                'data' => CertificateResource::collection($this->certificateService->getCertificates())
            ]
        );
    }
}
