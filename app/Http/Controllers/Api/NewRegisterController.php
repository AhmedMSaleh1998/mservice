<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewRegisterRequest;
use App\Http\Requests\Api\RetrieveRegistrationDocumentsRequest;
use Illuminate\Support\Facades\URL;
use Throwable;
use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Models\RegistrationRequest;
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
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('Registration failed. Please try again.'),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function retrieveDocuments(RetrieveRegistrationDocumentsRequest $request)
    {
        $registrationRequest = RegistrationRequest::query()
            ->where('national_id', trim((string) $request->input('national_id')))
            ->where('residence_mobile_1_country_code', trim((string) $request->input('residence_mobile_1_country_code')))
            ->where('residence_mobile_1', trim((string) $request->input('residence_mobile_1')))
            ->first();

        if (! $registrationRequest) {
            return response()->json([
                'success' => false,
                'message' => __('Unable to retrieve documents with provided data.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Documents retrieved successfully.'),
            'data' => [
                'reg_code' => $registrationRequest->reg_code,
                'pdf_urls' => $this->buildSignedPdfUrls($registrationRequest),
            ],
        ]);
    }

    public function downloadPdf(
        string $reg_code,
        string $document = RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST
    )
    {
        $registrationRequest = RegistrationRequest::query()
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

    private function buildSignedPdfUrls(RegistrationRequest $registrationRequest): array
    {
        $expiration = now()->addMinutes((int) config('services.registration_documents.signed_url_ttl', 60));

        return [
            'registration_request' => URL::temporarySignedRoute('register-pdf-document', $expiration, [
                'reg_code' => $registrationRequest->reg_code,
                'document' => RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST,
            ]),
            'license_request' => URL::temporarySignedRoute('register-pdf-document', $expiration, [
                'reg_code' => $registrationRequest->reg_code,
                'document' => RegistrationRequestPdfService::DOCUMENT_LICENSE_REQUEST,
            ]),
        ];
    }
}
