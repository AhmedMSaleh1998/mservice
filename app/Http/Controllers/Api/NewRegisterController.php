<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewRegisterRequest;
use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Resources\NewRegisterResource;
use Modules\Users\Services\NewRegisterService;
use Modules\Users\Services\RegistrationRequestPdfService;

class NewRegisterController extends Controller
{
    public function __construct(
        private readonly NewRegisterService $newRegisterService,
        private readonly RegistrationRequestPdfService $registrationRequestPdfService
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
                'data' => new NewRegisterResource($registrationRequest),
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

    public function downloadPdf(
        string $reg_code,
        string $document = RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST
    )
    {
        $registrationRequest = \Modules\Users\Models\RegistrationRequest::query()
            ->where('reg_code', $reg_code)
            ->first();

        if (! $registrationRequest) {
            return response()->json([
                'success' => false,
                'message' => __('Resource not found'),
            ], 404);
        }

        $result = $this->registrationRequestPdfService->generate($registrationRequest, $document);
        $fileName = $result['fileName'] ?? 'registration-request.pdf';
        $content = $result['content'] ?? '';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
