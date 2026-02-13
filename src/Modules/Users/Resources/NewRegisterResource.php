<?php

namespace Modules\Users\Resources;

use Illuminate\Http\Request;
use Modules\Core\CustomResource;
use Modules\Users\Services\RegistrationRequestPdfService;

class NewRegisterResource extends CustomResource
{
    public function data(Request $request): array
    {
        $pdfUrl = null;
        $pdfUrls = null;

        if ($this->resource->reg_code) {
            $pdfUrl = route('register-pdf', [
                'reg_code' => $this->resource->reg_code,
            ]);

            $pdfUrls = [
                'registration_request' => route('register-pdf-document', [
                    'reg_code' => $this->resource->reg_code,
                    'document' => RegistrationRequestPdfService::DOCUMENT_REGISTRATION_REQUEST,
                ]),
                'license_request' => route('register-pdf-document', [
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
