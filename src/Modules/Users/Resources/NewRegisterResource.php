<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Modules\Core\CustomResource;
use Modules\Users\Services\RegistrationRequestPdfService;

class NewRegisterResource extends CustomResource
{
    public function data(Request $request): array
    {
        $pdfUrls = null;

        if ($this->resource->reg_code) {
            $expiration = now()->addMinutes((int) config('services.registration_documents.signed_url_ttl', 60));

            $pdfUrls = [
                'registration_request' => URL::temporarySignedRoute('register-pdf-document', $expiration, [
                    'reg_code' => $this->resource->reg_code,
                    'document' => RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST,
                ]),
                'license_request' => URL::temporarySignedRoute('register-pdf-document', $expiration, [
                    'reg_code' => $this->resource->reg_code,
                    'document' => RegistrationRequestPdfService::DOCUMENT_LICENSE_REQUEST,
                ]),
            ];
        }

        return [
            'id' => $this->resource->id,
            'reg_code' => $this->resource->reg_code,
            'pdf_urls' => $pdfUrls,
        ];
    }
}
