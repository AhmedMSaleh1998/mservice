<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Procedures\Models\Procedure;
use Modules\Procedures\Resources\ProcedureListResource;
use Modules\Procedures\Resources\ProcedureResource;
use Modules\Procedures\Services\ProcedureService;

class ProceduresController extends Controller
{
    public function __construct(
        private readonly ProcedureService $procedureService
    ) {
    }

    public function index(): JsonResponse
    {
        $procedures = $this->procedureService->listActive();

        return response()->json([
            'status' => 200,
            'data' => ProcedureListResource::collection($procedures),
        ]);
    }

    public function show(Procedure $procedure): JsonResponse
    {
        $procedure = $this->procedureService->findActiveOrFail($procedure);

        return response()->json([
            'status' => 200,
            'data' => new ProcedureResource($procedure),
        ]);
    }
}
